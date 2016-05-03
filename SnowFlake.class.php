<?php
    /**
     * ID 生成策略
     * 毫秒级时间41位+机器ID 10位+毫秒内序列12位。
     * 0           41     51     64
    +-----------+------+------+
    |time       |pc    |inc   |
    +-----------+------+------+
     *  前41bits是以微秒为单位的timestamp。
     *  接着10bits是事先配置好的机器ID。
     *  最后12bits是累加计数器。
     *  macheine id(10bits)标明最多只能有1024台机器同时产生ID，sequence number(12bits)也标明1台机器1ms中最多产生4096个ID，
     *
     * @Author: xing.liu
     * @Date: 2016-05-03
     */
    class SnowFlake{
        private static $epoch = 1462264156000;
    
        public static function createID($machineId){
            /* 
            * Time - 41 bits
            */
            $time = floor(microtime(true) * 1000);
            
            /*
            * Substract custom epoch from current time
            */
            $time -= SnowFlake::$epoch;
        
            /*
            * Create a base and add time to it
            */
            $base = decbin(pow(2,40) - 1 + $time);
            /*
            * Configured machine id - 10 bits - up to 512 machines
            */
            $machineid = decbin(pow(2,9) - 1 + $machineId);
            
            /*
            * sequence number - 12 bits - up to 2048 random numbers per machine
            */
            $random = mt_rand(1, pow(2,11)-1);      
            $random = decbin(pow(2,11)-1 + $random);
            
            /*
            * 拼装$base
            */
            $base = $base.$machineid.$random;
            
            /*
            * 讲二进制的base转换成long
            */
            $base = bindec($base);

            $id = sprintf('%.0f', $base);
            
            return $id;
        }
    }
?>
