<?php
	/*
	*@Description:多线程的简单应用
	*@Author:学在囧途
	*@Time:2013.12.16 21:35:24
	*/
	class myThread extends Thread{
		public $url;
		public function __construct($url){
			$this->url = $url;
		}
		public function run(){
			 $data   = file_get_contents($this->url);
			 $domain = explode(".", $this->url);
			 file_put_contents("html/".$domain[1].".txt", $data);
			 echo "Thread ".myThread::getThreadId()." Catch ".$this->url." is done!\n";
		}
	}

	set_time_limit(0);
	$start = microtime(true);
	$url = array(
			"http://www.uc.cn",
			"http://www.163.com",
			"http://www.taobao.com",
			"http://www.tmall.com",
			"http://www.hao123.com",
			"http://www.zhihu.com",
			"http://www.12306.cn",
			"http://www.sinaapp.com",
			"http://www.weibo.com",
			"http://www.qq.com"
		);	
	for($i = 0; $i < 10; $i++){
		$myThread[$i] = new myThread($url[$i]);
		$myThread[$i] -> start();
	}
	//回收线程资源到主线程，使整个过程视为串行
	for($i = 0; $i < 10; $i++){
		$myThread[$i] -> join();
	}
	}
	$end = microtime(true);
	$cost= $end - $start;
	echo "Running use ".$cost."s\n";
?>
