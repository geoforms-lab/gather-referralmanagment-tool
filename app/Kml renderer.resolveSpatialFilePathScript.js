
if(strpos($file,'{datawarehouse}')===0){
	    //throw new \Exception(print_r($args, true));
	    
	    
	    $id=explode('{project:', $file);
	    $id=array_pop($id);
	    $id=explode('}',$id);
	    $id=array_shift($id);
	    
	    // if(intval($id)<=0){
	    //     $id=30;
	    // }
	    
	    
	   
	    $gather=GetPlugin('ReferralManagement');
	    $data=$gather->getProjectData($id);


	    
	    if(isset($data['metadata']->file->file)){

	    	$paths=$gather->getParameters('datawarehousePaths', array());

	    	foreach($paths as $dir){
	    		$dir=rtrim(trim($dir),'/');
	    		if((!empty($dir))&&is_dir($dir)){
	    			$realpath=realpath($dir.'/'.$data['metadata']->file->file);
			        if(file_exists($realpath)){
			            return $realpath;
			        }
	    		}
	    	}

	        
	        throw new \Exception('file does not exist: '+$realpath);
	    }
	    
	    return $data['metadata']->file;
	    
	}

	return $file;