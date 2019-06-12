<?php
header("Content-Type:text/html;charset=utf-8");

/**
 * 抓取统计局2018年中国行政编码
 * @author Xiaowu@baisidu.com
 * @description 依赖phpQuery、curl、mbstring
 */

include( "./phpQuery/phpQuery.php" );

class AreaSpider{

	/**
	 * 开辟内存空间
	 */

	private $__MEMORY_LIMIT__ = "2048M";

	/**
	 * 行政编号补位长度
	 */

	private $__CODE_LENG__ = 12 ;

	/**
	 * 每次抓取URL的停顿时长，单位秒
	 * 防止被冻结访问权限
	 */

	private $__SLEEP_LONG__ = 5;

	/**
	 * 写入数据到文件，相对当前文件路径
	 */

	private $__WRITE_FILE__ = "area_code.txt";

	/**
	 * 不同级别的TR的class 值
	 */

	private $__LEVEL_TR_CLASS__ = [
		'',
		// 1,省份
  		'.provincetr',
  		// 2,城市
  		'.citytr',
  		// 3,县区
  		'.countytr',
  		// 4,镇
  		'.towntr',
  		// 5,乡、村委会
  		'.villagetr'
	];

	/**
	 * 2018年行政编码入口路径
	 */

	private $__GOV_STATS_2018_URL__ = "http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2018/";

	/**
	 * 统计局的页面编码
	 */

	private $__GOV_STATS_ENCODE__ = "GBK";

	/**
	 * 当前页面的编码
	 */

	private $__DEV_ENCODE__       = "UTF-8";

	/**
	 * 初始化爬虫
	 */

	public function __construct(){
		@set_time_limit( 0 );
		@ini_set( 'memory_limit' , $this->__MEMORY_LIMIT__);
	}

	/**
	 * 行政编码补位
	 * @param $code string 需要补位的行政编码
	 * @return string 
	 */

	private function areaCodePad( $code ){
		return str_pad( $code , $this->__CODE_LENG__ , '0' , STR_PAD_RIGHT );
	}

	/**
	 * 字符编码转换
	 * @param $str string 需要转码的字符
	 * @return string 
	 */

	private function convertEncode( $str ){
		if( $this->__DEV_ENCODE__ == $this->__GOV_STATS_ENCODE__  ){
			return $str ;
		}
		return mb_convert_encoding( $str , $this->__DEV_ENCODE__ , $this->__GOV_STATS_ENCODE__ );
	}

	/**
	 * 获取抓取页面的内容，并转换对应的字符集，由于phpQuery是全匹配正则，所以需要转换成小写
	 * @param $url string 抓取的页面地址
	 * @return string
	 */

	private function getPageContent( $url ){
		sleep( $this->__SLEEP_LONG__ );
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true  );
		$callback = curl_exec( $ch );
		curl_close( $ch );
		return strtolower(  $this->convertEncode( $callback ) );
	}

	/**
	 * 获取URL的基础路径
	 * @param $url string 
	 * @return string 
	 */

	private function getUrlBasePath( $url ){
		$path = pathinfo($url );
		return $path['dirname'] . '/';
	}

	/**
	 * 把数据追加到文件中
	 * @param $str string  数据
	 */

	private function writeDataToFile( $str ){
		$fp = fopen( dirname(__FILE__) . '/' . $this->__WRITE_FILE__ , 'a' );
		fwrite( $fp , $str );
		fclose($fp );
	}

	/**
	 * 分析href
	 * @param $url string 抓取页面的URL
	 * @param $LinkDocument queryDocument  链接的对象
	 * @return array  
	 			 code 编码
	 			 url  子级的抓取的URL
	 */

	private function analysisLink( $url , $LinkDocument ){
		$linkAddress 		= phpQuery::pq( "a" , $LinkDocument )->attr('href');
		$pathSplitLink 		= explode( '/' , $linkAddress );
		$pathSplitCount     = count( $pathSplitLink )-1;
		$extSplitLink       = explode( '.' , $pathSplitLink[$pathSplitCount] );
		$code  				= $this->areaCodePad( $extSplitLink );
		$nodeUrl   			= $this->getUrlBasePath( $url ) . $linkAddress;
		return [
			'code' => $code ,
			'url'  => $nodeUrl 
		];
	}

	/**
	 * 获取省份数据
	 * @param $url string  抓取的URL地址
	 * @param $level int   所属级别
	 */

	private function getProvince( $url , $level ){
		$trClass 	 = $this->__LEVEL_TR_CLASS__[$level];
		$pageContent = $this->getPageContent( $url );
		if( $pageContent ){
			$queryDocument = phpQuery::newDocument( $pageContent ); 
			$trCount       = phpQuery::pq( $trClass , $queryDocument )->size();
			if( $trCount ){
				for( $i = 0 ; $i < $trCount ; $i++  ){
					$tdCount =  phpQuery::pq( $trClass . ":eq($i) td" , $queryDocument  )->size();
					if( $tdCount ){
						for( $j = 0 ; $j < $tdCount ; $j++ ){
							$tdContent =  phpQuery::pq( $trClass . ":eq($i) td:eq($j)" , $queryDocument  )->html();
							if( $tdContent ){
								$provinceName  	= strip_tags( $tdContent );	
								$tdDocument 	= phpQuery::newDocument( $tdContent );
								$result 		= $this->analysisLink( $url , $tdDocument )
								$writeStr   	= $result['code'] . ',' . $provinceName . "\n";
						 		$this->writeDataToFile( $writeStr  );
						 		$nextLevel  	= $level + 1 ;
						 		$this->getNodeData( $result['url']  , $nextLevel );
							}
						}
					}
				}
			}
		}	
	}

	/**
	 * 获取子级数据
	 * @param $url string  抓取的URL地址
	 * @param $level int   所属级别
	 */

	private function getNodeData( $url , $level ){
		$trClass 	 = $this->__LEVEL_TR_CLASS__[$level];
		$pageContent = $this->getPageContent( $url );
		if( $pageContent ){
			$queryDocument = phpQuery::newDocument( $pageContent ); 
			$trCount       = phpQuery::pq( $trClass , $queryDocument )->size();
			if( $trCount ){
				for( $i = 0 ; $i < $trCount ; $i++ ){
					$nextNodeUrl= "";
					$category   = "";
					$codeHtml   = phpQuery::pq($trClass . ":eq($i) td:eq(0)" , $queryDocument  )->html();
					$code       =  $this->areaCodePad( strip_tags( $codeHtml ) );
					if( $level == 5 ){
						$areaTdindex = 2 ;
						$category    = strip_tags( phpQuery::pq( $trClass . ":eq($i) td:eq(1)" , $queryDocument  )->html() );
					}
					else {
						$areaTdindex = 1 ;
					}
					$areaName 		= strip_tags( phpQuery::pq( $trClass . ":eq($i) td:eq(" . $areaTdindex . ")" , $queryDocument  )->html() );
					$writeStr       = $code . "," . $areaName . ( $category ? "," . $category : "" ) . "\n";
					$this->writeDataToFile( $writeStr  );
					if( preg_match( '/\<a/', $codeHtml ) ){
						$linkDocument = phpQuery::newDocument( $codeHtml );
						$result       = $this->analysisLink( $url , $linkDocument );
						$nextNodeUrl  = $result['url'];
						if( $nextNodeUrl ){
							$nextLevel  	= $level+1 ;
							$this->getNodeData( $nextNodeUrl , $nextLevel );
						}
					}
				}
			}
		}
	}

	/**
	 * 抓取执行入口
	 */

	public static function start(){
		$class  = __CLASS__ ;
		$spider = new $class();
		$level  = 1 ;
		$url    = $this->__GOV_STATS_2018_URL__ . 'index.html';
		$spider->getProvince( $url  , $level );
	}

}

// 执行抓取

AreaSpider::start();
