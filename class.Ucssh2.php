<?php
	/*
	*Description:SSH2操作类
	*/
	class Ucssh2{
		private $link;                                 //数据库连接句柄
		private $info      = array();                  //校验参数
		private $hash;                                 //文件hash值，用于校对文件
		private $failList  = array();                  //失败信息
		private $retryTimes= 3;                        //失败重试次数
		private $why;                                  //失败原因
		private $hostInfo;
		private $timeout   = 600; 
		private $sourcePath;

		//构造函数
		public function __construct($sourcePath, $hostInfo){	
			$this->hostInfo   = $hostInfo;
			$this->sourcePath = $sourcePath;
		}

		//处理上传逻辑
		private function doUpload($path, $id){
			$this->checkHostInfo();
                        $this->checkPath($path);
			$pathExt       = pathinfo($path);
			$pathExtDir    = $pathExt['dirname']."/";
                        $filename      = $pathExt['basename'];
                        $sourcePath    = $this->sourcePath.$filename;
                        $this->hash    = md5(file_get_contents($sourcePath));
                        $i             = 0;             //上传成功计数
                        foreach($this->hostInfo as $key => $value){
				$targetPath    = $this->hostInfo[$value['hostIp']]['targetPath']; 
				$this->UpdateUploadStatusAndTime($id, 1, "Uploading...");
                        	$status = $this->sshAction($value['hostIp'], $value["hostPort"], $value["hostUser"], $this->hostInfo[$value['hostIp']]["hostPWD"], $filename, $sourcePath, $targetPath.$pathExtDir);
                        	if($status){
                                	$Msg[$i]['status'] = "Upload ".$filename." to ".$value['hostIp']." successfully...";
                        	}else{
					$this->failList[$value['hostIp']]['id']           = $id;
					$this->failList[$value['hostIp']]['filename']     = $filename;
                                	$this->failList[$value['hostIp']]['hostIp']       = $value['hostIp'];
                                	$this->failList[$value['hostIp']]['hostUser']     = $value['hostUser'];
                                	$this->failList[$value['hostIp']]['hostPWD']      = $this->hostInfo[$value['hostIp']]['hostPWD'];
                                	$this->failList[$value['hostIp']]['hostPort']     = $value['hostPort'];
                                	$this->failList[$value['hostIp']]['targetPath']   = $targetPath.$pathExtDir;
                                	$this->failList[$value['hostIp']]['sourcePath']   = $sourcePath;
                                	$this->failList[$value['hostIp']]['retryTimes']   = 0;
                                	$this->failList[$value['hostIp']]['failReason']   = $this->why;
                        	}
                                $i++;
                        }
		}
		

		//更新安装包的状态和操作时间
		private function UpdateUploadStatusAndTime($id, $status, $remark){
			$data['modify_time'] = time();
			$data['status']      = $status;
			$data['remark']      = $remark;
			UploadPackageFileSync::Update("ucdl_upload_package_file_sync", $data, "id = $id");
		}

		//执行方法
		public function run($id){
			$idMsg = $this->getUploadStatus($id);
			$Msg   = array();
			if($idMsg[0]['status'] == 0){
				$this->doUpload($idMsg[0]['targetpath'], $id);
			}else if($idMsg[0]['status'] == 1){
				$checktime = time();
				$plus      = $checktime - $idMsg[0]['modify_time'];
				if($plus >= $this->timeout){
					$this->doUpload($idMsg[0]['targetpath'], $id);
				}else{
					$Msg['status'] = "The file is uploading...";
					echo json_encode($Msg);
					return false;
				}
			}else if($idMsg[0]['status'] == 2){
				$remarkData = json_decode($idMsg[0]['remark'], true);
				foreach($remarkData as $key => $value){
					$targetPath = $value['targetPath'];
					$sourcePath = $value['sourcePath'];
					$filename   = $value['filename'];
					$this->hash = md5(file_get_contents($sourcePath));
					$this->UpdateUploadStatusAndTime($value['id'], 1, "Uploading...");
                                	$status = $this->sshAction($value['hostIp'], $value["hostPort"], $value["hostUser"], $this->hostInfo[$value['hostIp']]['hostPWD'], $filename, $sourcePath, $targetPath);
                                	if($status){
                                        	$Msg[$i]['status'] = "Upload ".$filename." to ".$value['hostIp']." successfully...";
                                	}else{
						$this->failList[$value['hostIp']]['id']           = $value['id'];
						$this->failList[$value['hostIp']]['id']           = $filename;
                                        	$this->failList[$value['hostIp']]['hostIp']       = $value['hostIp'];
                                        	$this->failList[$value['hostIp']]['hostUser']     = $value['hostUser'];
                                        	$this->failList[$value['hostIp']]['hostPWD']      = $this->hostInfo[$value['hostIp']]['hostPWD'];
                                        	$this->failList[$value['hostIp']]['hostPort']     = $value['hostPort'];
                                        	$this->failList[$value['hostIp']]['targetPath']   = $targetPath;
                                        	$this->failList[$value['hostIp']]['sourcePath']   = $sourcePath;
                                        	$this->failList[$value['hostIp']]['retryTimes']   = 0;
                                        	$this->failList[$value['hostIp']]['failReason']   = $this->why;
                                	}
				}
					
			}else if($idMsg[0]['status'] == 3){
				$Msg['status'] = "The file has uploaded successfully";
				echo json_encode($Msg);
				return false;
			}else{
				$Msg['status'] = "Unknown Error...";
				echo json_encode($Msg);
				return false;
			}

			$failListNum = count($this->failList);
			$count       = 0;
			//检查重试队列
			while(true){
				$i = 0;
				//如果存在失败任务则重试
				if(!empty($this->failList) && is_array($this->failList)){
					foreach ($this->failList as $key => $value) {
						//当重试次数小于系统设置重试次数时，进行重试
						if($this->retryTimes > $value['retryTimes']){
							$status = $this->sshAction($value['hostIp'], $value["hostPort"], $value["hostUser"], $this->hostInfo[$value['hostIp']]["hostPWD"], $filename, $value["sourcePath"], $value["targetPath"]);
							if($status){
								unset($this->failList[$i]);
								rsort($this->failList);
							}else{
								$this->failList[$value['hostIp']]["retryTimes"] = $value['retryTimes'] + 1;
							}	
						}else{
							$count += 1;
						}
					}
				}
				if($count == $failListNum){
					break;
				}
			}
			if(empty($this->failList)){
				$this->UpdateUploadStatusAndTime($id, 3, "success...");
			}else{
				$this->UpdateUploadStatusAndTime($id, 2, json_encode($this->failList));	
			}
			echo json_encode($Msg);
		}



		//获取ID的状态
		private function getUploadStatus($id){
			if(!empty($id)){
				$checktime = time();
                        	return UploadPackageFileSync::Get("ucdl_upload_package_file_sync", "id = $id");
			}else{
				$this->why = "Id is empty...";
				return false;
			}
		}


		//检查$hostInfo
		private function checkHostInfo(){
			if(!empty($this->hostInfo)){
				if(is_array($this->hostInfo)){
					foreach($this->hostInfo as $key => $value){
						if(is_array($value)){
							foreach($value as $k => $v){
								if(empty($v)){
									$this->why = $k." is empty...";
									return false;
								}
							}
						}else{
							$this->why = $key." is empty...";
							return false;
						}	
					}
				}else{
					$this->why = "HostInfo is not a array...";
					return false;
				}	
			}else{
				$this->why = "HostInfo is empty...";
				return false;
			}
		} 


		//检查路径
		private function checkPath($path){
			if(empty($path)){
				$this->why = "Path is empty...";
				return false;
			}
		}


		//执行ssh操作
		private function sshAction($hostAddress, $port, $user, $pwd, $filename, $sourcePath, $targetPath){
			$connection = ssh2_connect($hostAddress, $port);
			if(!$connection){
				$this->why = "Unable to connect to ".$hostAddress." on port ".$port;
				return false;
			}
			$login      = ssh2_auth_password($connection, $user, $pwd);
			if(!$login){
				$this->why = "Server verification failed, Please check the HostInfo";
				return false;
			}
			$mkdir = ssh2_exec($connection, "mkdir -p ".$targetPath);
			if(!$mkdir){
				$this->why = "mkdir failed...";
				return false;	
			}
			$status = ssh2_scp_send($connection, $sourcePath, $targetPath.$filename, 0755);
			if(!$status){
				$this->why = "It fail to upload the file, please find out your reason such as network...";
				return false;
			}
			$mystr = "";
			for($i = 0; $i < strlen($filename); $i++) {
				if($filename[$i] == "(" || $filename[$i] == ")"){
					$mystr .= "\\".$filename[$i];
				}else{
					$mystr .= $filename[$i];
				}
			}
			$stream = ssh2_exec($connection, 'md5sum '.$targetPath.$mystr);
			stream_set_blocking($stream, true);
			$msg = stream_get_contents($stream);
			ssh2_exec($connection, 'exit');
			$streaminfo = explode(" ", $msg);
			if($streaminfo[0] === $this->hash){
				return true;
			}else{
				$this->why = "Two file's hash is not equals...";
				return false;
			}
		}
		
		public static function getStatus($id){
			$Msg = UploadPackageFileSync::Get("ucdl_upload_package_file_sync", "id = $id");
			return $Msg[0]['status'];
		}

	}	
?>
