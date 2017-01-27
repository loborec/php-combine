<?php

    class Combine{

        const robocopy='c:\Apps\scripts\bin\robocopy.exe';

        public static function init($sources, $id, $dir){
            $result=false;

            //$less_array=self::extract_keys($sources,'less');


            $js_array=self::extract_keys($sources,'js');
            $css_array=self::extract_keys($sources,'css'); 
            $copy_array=self::extract_keys($sources,'copy'); 
            //dump($sources);
            //self::less_init($less_array);

            $r1=self::js_init($js_array, $dir, $id);
            $r2=self::css_init($css_array, $dir, $id); 

            if($r1 || $r2){ 
                self::copy_init($copy_array);
                $result=true;
            }  
            return $result;
        }

        private static function extract_keys($items, $key){
            $result=[];
            foreach ($items as $item){
                if (isset($item[$key])){
                    $result=array_merge($result, $item[$key]);   
                }
            }
            return $result;
        }

        private static function copy_init($sources){
            foreach ($sources as $source){
                $source_filename=$source['source'];
                $target_filename=$source['target'];
                $params=isset($source['options'])?$source['options']:'';
                if (! file_exists($source_filename))
                    die('Not found: '.$source_filename);
                //
                if (file_exists(self::robocopy)){
                    exec(self::robocopy.' "'.$source_filename.'" "'.$target_filename.'" '.$params);
                }
                else
                {
                    xcopy($source_filename, $target_filename); 
                }
            }
        }

        private static function js_init($sources, $dir, $id){


            $x = '';
            foreach($sources as $source){ 

                if (! isset($source['source'])){
                    echo 'Item not set<br>'.print_r($source);
                    die();
                }    

                $x.= (string)filemtime($source['source']);



            }
            $md5=md5($x);

            $output_file="${dir}${id}.${md5}.js";


            if (! file_exists($output_file)){

                //pobrišemo sve .js datoteke u folderu
                del_files($dir."$id.*.js");


                $table=array();
                $js = '';
                foreach($sources as $source){ 
                    if (strpos($source['source'],'min.js')!=0){ 
                        $j= file_get_contents($source['source']); 

                        $js .=$j;
                        $table[]=array(extract_file_name($source['source']), mb_strlen($j),'');   
                    } 
                    else{  
                        $j= \JShrink\Minifier::minify(file_get_contents($source['source']));
                        $js .=$j;
                        $table[]=array(extract_file_name($source['source']), filesize($source['source']),  mb_strlen($j));  
                    }      
                    $js .=';';     
                }

                $header=array();
                $header[]='/*!';
                $header[]=str_pad('', 64,chr(151));
                $header[]='|'.str_pad('dubravkodev.com javascript compactor',62,' ', STR_PAD_BOTH).'|'; 
                $header[]=str_pad('', 64,chr(151));
                $header[]='|'.str_pad('File Name',40).'|'.str_pad('Orig. size',10,' ',STR_PAD_LEFT).'|'.str_pad('Comp. size',10,' ',STR_PAD_LEFT).'|'; 
                $header[]=str_pad('', 64,chr(151));
                for ($i = 0; $i <= count($table)-1 ; $i++) {
                    $header[]='|'.str_pad($table[$i][0],40).'|'.str_pad($table[$i][1],10,' ',STR_PAD_LEFT).'|'.str_pad($table[$i][2],10,' ',STR_PAD_LEFT).'|';              
                }
                $header[]=str_pad('', 64, chr(151));
                $header[]='*/';
                $header[]='';

                file_put_contents($output_file, implode("\n",$header).$js);
                // file_put_contents($md5_file, $md5); 

                return true;
            }
            else
                return false;
        }

        private static function valid_ext($ext){
            $ext=strtolower($ext);
            return ($ext=='gif') or ($ext=='png') or ($ext=='jpg') or ($ext=='jpeg');      
        } 


        private static function css_init($sources, $dir, $id){
            $x=''; 


            /**** md5 slika! ****/
            foreach($sources as $source){ 
                $dataURI=isset($source['dataURI']) and ($source['dataURI']===true); 
                if ($dataURI){
                    $css = file_get_contents($source['source']);
                    // preg_match_all('/url\((.*)\)/', $css, $matches);

                    preg_match_all('~\bbackground-image?\s*:(.*?)\(\s*(\'|")?(?<image>.*?)\3?\s*\)~i',$css,$matches);

                    foreach ($matches[0] as $match){

                        $n1=strpos($match, '(');
                        $n2=strpos($match, ')');
                        $image_name=substr($match, $n1+1, $n2-$n1-1);
                        $vowels = array("'", '\"', '"');   
                        $image_name = str_replace($vowels, "", $image_name);


                        $ext=extract_file_ext($image_name); 
                        if (self::valid_ext($ext)){
                            $result=self::image_file($image_name, $source);
                            if ($result!==false){
                                $size=filesize($result); 
                                $dataURI_max_filesize=isset($source['dataURI_max_filesize'])?$source['dataURI_max_filesize']:10000;
                                if ($size<$dataURI_max_filesize){   
                                    $x.= (string)filemtime($result); 
                                }
                            } 
                            else
                            {
                                die("Image not found: '$image_name' in '".$source['source']."'"); 
                            } 
                        } 
                    }
                }
            }



            foreach($sources as $source){ 
                $x.= (string)filemtime($source['source']);
            }
            $md5=md5($x);


            $output_file="${dir}${id}.${md5}.css";

            if (! file_exists($output_file)){
                //pobrišemo sve .css datoteke u folderu
                del_files($dir."$id.*.css");

                $css = '';
                $table=array();


                $output_css='';
                foreach($sources as $source){ 
                    $dataURI=isset($source['dataURI']) and ($source['dataURI']===true); 
                    //
                    if (strpos($source['source'],'min.css')!=0) 
                    {
                        $css = file_get_contents($source['source']);  
                        $output_css.=$css;  

                        $table[]=array(extract_file_name($source['source']), mb_strlen($css),'', '');  
                    }
                    else
                    {   
                        $options = array(
                            'compress'=>true, 
                            'relativeUrls'=>false,
                            'cache_dir'=>APP_ROOT.'/assets/cache'
                        );
                        $parser = new Less_Parser($options);
                        //$parser->setImportDirs([APP_LIBRARY.'/loborec/yii-library/css']);
                        $parser->parseFile($source['source'], '');
                        $css=$parser->getCss();  

                        if ($dataURI){
                            $output_css.=self::uri_file_get_contents($css, $source); 
                        }
                        else  {   
                            $output_css.=$css; 
                        }
                        //


                        $table[]=array(extract_file_name($source['source']), filesize($source['source']),  mb_strlen($css), $dataURI?'Yes':'');  
                    } 
                }

                $header=array();
                $header[]='/*!';
                $header[]=str_pad('', 72, chr(151));
                $header[]='|'.str_pad('dubravkodev.com css compactor',71,' ', STR_PAD_BOTH).'|'; 
                $header[]=str_pad('', 72,chr(151));
                $header[]='|'.str_pad('File Name',40).'|'.str_pad('Orig. size',10,' ',STR_PAD_LEFT).'|'.str_pad('Comp. size',10,' ',STR_PAD_LEFT).'|'.str_pad('dataURI',7,' ',STR_PAD_LEFT).'|'; 
                $header[]=str_pad('', 72,chr(151));
                for ($i = 0; $i <= count($table)-1 ; $i++) {
                    $header[]='|'.str_pad($table[$i][0],40).'|'.str_pad($table[$i][1],10,' ',STR_PAD_LEFT).'|'.str_pad($table[$i][2],10,' ',STR_PAD_LEFT).'|'.str_pad($table[$i][3],7,' ',STR_PAD_BOTH).'|';              
                }
                $header[]=str_pad('', 72, chr(151));
                $header[]='*/';
                $header[]='';

                file_put_contents($output_file, implode("\n",$header).$output_css);
                return true;
            }
            else
                return false;
        }

        private static function uri_file_get_contents($css, $source){

            //$css=file_get_contents($source['source']);
            //preg_match_all('~\bbackground-image?\s*:(.*?)\(\s*(\'|")?(?<image>.*?)\3?\s*\)~i',$css,$matches);
            //return preg_replace_callback( '/url\((.*)\)/', 
            return preg_replace_callback("/url\((.*?)\)/is", 
                function($m) use ($source) { return self::preg_replacex($m[0], $source); },
                $css); 
        }

        private static function preg_replacex($match, $source) {


            //$vowels = array("'", '\"', '"', 'url(', ')');
            //$image_name = str_replace($vowels, "", $match);
            $n1=strpos($match, '(');
            $n2=strpos($match, ')');
            $image_name=substr($match, $n1+1, $n2-$n1-1);
            $vowels = array("'", '\"', '"');   
            $image_name = str_replace($vowels, "", $image_name);

            $ext=extract_file_ext($image_name); 
            if (self::valid_ext($ext)){
                $result=self::image_file($image_name, $source);
                if ($result!==false){
                    $size=filesize($result);
                    $dataURI_max_filesize=isset($source['dataURI_max_filesize'])?$source['dataURI_max_filesize']:10000;
                    if ($size<$dataURI_max_filesize){ 
                        return "url(data:image/${ext};base64,".base64_encode(file_get_contents($result)).')';
                    }
                    {
                        return "url('${image_name}')";     
                    }
                }
                else    
                    return "url('${image_name}')";
            }
        }

        private static function image_file($image_name, $source){

            $search_paths=array();
            $search_paths[]=extract_file_dir($source['source']);

            if (isset($source['dataURI_search_paths'])){
                $search_paths=array_merge($search_paths, $source['dataURI_search_paths']);
            }

            foreach ($search_paths as $search_path)
            {
                $file=normal_dir($search_path).$image_name;
                if (file_exists($file)){
                    return $file;
                }  
            }
            return false;
        }

}