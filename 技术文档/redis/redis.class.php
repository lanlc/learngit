<?php
	class My_Redis
	{
		private $redis;
		function __construct($host='127.0.0.1', $port=6379, $pwd='lanlc')
		{
			$this->redis = new Redis();
			$this->redis->connect($host, $port);
			$this->redis->auth($pwd);
			//return $this->redis;
		}
		
		/*
		String
		================================================================================================
		*/
		
		/*
		 *	@ set a key
		 *	@param $timeType	expireat time type, default sec,else strtotime
		 *
		 */
		public function set($key, $value, $timeType=0, $timeOut=0)
		{
			$resultRedis = $this->redis->set($key, $value);
			if ($timeType>0)
			{
				if($timeOut>0)
				{
					$this->redis->expireat('$key', $timeOut);
				}
			}else
			{
				if($timeOut>0)
				{
					$this->redis->expire('$key', $timeOut);
				}
			}
			
			return $resultRedis;
		}
		
		/*
		 *	@ get a key value
		 *	
		 *
		 */
		public function get($key)
		{
			return $this->redis->get($key);
		}
		
		/*
		 *	@ is key seted
		 *	
		 *
		 */
		public function exists($key)
		{
			return $this->redis->exists($key);
		}
		
		/*
		 *	@ delete a key 
		 *	
		 *
		 */
		public function del($key)
		{
			return $this->redis->del($key);
		}
		
		/*
		List
		================================================================================================
		*/
		
		/*
		 *	@ push a value to the list by light 
		 *	
		 *
		 */
		public function lpush($key, $value)
		{
			return $this->redis->lpush($key, $value);
		}
		
		/*
		 *	@ push a value to the list by right 
		 *	
		 *
		 */
		public function rpush($key, $value)
		{
			return $this->redis->rpush($key, $value);
		}
		
		/*
		 *	@ get datas from a list between 
		 *	
		 *
		 */
		public function lrange($key, $start=0, $end=-1)
		{
			return $this->redis->lrange($key, $start, $end);
		}
		
		/*
		 *	@ get the lengths of a list
		 *	
		 *
		 */
		public function llen($key)
		{
			return $this->redis->llen($key);
		}
		
		/*
		 *	@ delete datas which not in the between
		 *	
		 *
		 */
		public function ltrim($key, $start, $end)
		{
			return $this->redis->ltrim($key, $start, $end);
		}
		
		/*
		Set
		================================================================================================
		*/
		
		/*
		 *	@ add a value to the set which named $key
		 *	
		 *
		 */
		public function sadd($key, $value)
		{
			return $this->redis->sadd($key, $value);
		}
		
		/*
		 *	@ get counts of the set which named $key
		 *	
		 *
		 */
		public function scard($key)
		{
			return $this->redis->scard($key);
		}
		
		/*
		 *	@ get all datas of the set which named $key
		 *	
		 *
		 */
		public function smembers($key)
		{
			return $this->redis->smembers($key);
		}
		
		/*
		 *	@ delete the value  from the set which named $key
		 *	
		 *
		 */
		public function srem($key,$value)
		{
			return $this->redis->srem($key,$value);
		}
		
		/*
		 *	@ delete the value  from the set which named $key
		 *	
		 *
		 */
		public function sremAll($key)
		{
			$setData = $this->smembers($key);
			foreach ($setData AS $arrKey=>$val)
			{
				 $this->redis->srem($key,$val);
			}
		}
	}	

?>