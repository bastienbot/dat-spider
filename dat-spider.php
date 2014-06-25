<?php
ob_start();
/**
*Website crawler / Email parser / Motherfuckin' Spiderman
*V. 0.4a
*Bastien Botella.
*/

session_start();
require ("simple_html_dom.php");
echo '<head>
<meta charset="UTF-8">
</head><body>';


function flattenn(array $array) {
    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;
}

class email_parser {

	//Declaring main domain being scanned
	public $_domain;
	//Declaring vars which we will use to start our DOM instances
	private $_html;
	private $_html2;
	//Vars containing results array from single page  
	private $_array_ftext = array();
	private $_array_fanchors = array();
	//Var containing link list array
	private $_array_page = array();
	//Var containing final email result
	private $_array_final = array();

	





	public function __construct($url) {
		$this->setDomain($url);
	}



	//Setter Domain to parse
	public function setDomain($url) {
		$this->_domain = $url;
	}
	//Getter DOM full html
	public function getHtml() {
		return $this->_html;
	}
	//Getter DOM plain text (no html tags, only output shown)
	public function getHtml2() {
		return $this->_html2;
	} 
	//Getter 
	public function getArray_ftext() {
		return $this->_array_ftext;
	}
	//Getter 
	public function getArray_fanchors() {
		return $this->_array_fanchors;
	}
	//Getter 
	public function getArray_page() {
		return $this->_array_page;
	}
	//Getter
	public function getArray_final() {
		return $this->_array_final;
	}



	public function escape() {
		$_SESSION['next_page'] = $_SESSION['page_list'][0];
		$_SESSION['page_list_done'][] = $_SESSION['page_list'][0] . ' ----- FAIL TO CONNECT. Debug purpose : Fucked up url';
		array_splice($_SESSION['page_list'], 0, 1);
		header( "refresh:2;url=" . $_SERVER['PHP_SELF'] );
	}



	/**
	*Function starting session which will enclose variables
	*/
	public function init() {

		//initializing session vars
		if (!isset($_SESSION['page_list']) || empty($_SESSION['page_list'])) {
			$_SESSION['page_list'] = array();
		}
		if (!isset($_SESSION['page_list_done']) || empty($_SESSION['page_list_done'])) {
			$_SESSION['page_list_done'] = array();
		}
		if (isset($_SESSION['started'], $_SESSION['next_page'])) {
			$this->_html = file_get_html($_SESSION['next_page']) or die(
				$this->escape()
				);
			$this->_html2 = file_get_html($_SESSION['next_page'])->plaintext;
		} else {
			var_dump($_SESSION);
			echo '<br />started et session nexiste pas<br /><br /><br />';
			$_SESSION['mail_list'] = array();
			$_SESSION['started'] = 2;
			$this->_html = file_get_html($this->_domain);
			$this->_html2 = file_get_html($this->_domain)->plaintext;
		}

	}

	/**
	*Check if page returns 404, if it does, we skip the next function, go straight to header
	*/
	public function checkhttp() {
		if ($_SESSION['started'] == 1) {
			$headers = @get_headers($_SESSION['next_page'], 1);
				if(!preg_match('/(200|202|300|301|302)/', $headers[0]) && !preg_match('/(html)/', $headers['Content-Type'][0])){
					$_SESSION['next_page'] = $_SESSION['page_list'][0];
					$_SESSION['page_list_done'][] = $_SESSION['page_list'][0] . ' ----- FAIL TO CONNECT. Debug purpose : ' . $headers[0] . $headers['Content-Type'];
					array_splice($_SESSION['page_list'], 0, 1);
					foreach ($_SESSION['page_list_done'] as $value) {
						echo $value . '<br />';
					}
					echo $headers[0] . '<br />';
					var_dump($headers);
					header( "refresh:2;url=" . $_SERVER['PHP_SELF'] );
					//exit();
				} elseif (preg_match('/^.*(geolocalisation)$/i', $_SESSION['next_page'])) {
					echo 'We skip that one <br /><br />';
					header( "refresh:2;url=" . $_SERVER['PHP_SELF'] );
				}
		}
	}

	public function purge() {
		$_SESSION['backup'] = $_SESSION['page_list'];
		$_SESSION['purge'] = array();
		foreach($_SESSION['page_list'] as $value)
		if (!preg_match('/^.*(geolocalisation)$/i', $value)) {
			array_push($_SESSION['purge'], $value);
		}
		$_SESSION['page_list'] = $_SESSION['purge'];
		foreach ($_SESSION['page_list'] as $value) {
			echo $value;
			echo '<br />';
		}

	}

	/**
	*Function parsing a page looking for internal links
	*This function will add yet unlisted links to _SESSION['page_list']
	*/
  public function links_listing() {
	$single_list = array();
	$regexa = '/^(http:|https:|mailto:|ftp:|#)/';
	$regexb = '/^(' . preg_quote($this->_domain, '/') . ')/';
	foreach ($this->_html->find('a') as $links) {
	  $any_link = $links->href;
	  if (!preg_match($regexa, $any_link) || preg_match($regexb, $any_link)) {
		$single_list[] = $any_link;
	  }
	}
	var_dump($this->_domain);
	//Associating each link to the page list array if it's not part of it yet
	foreach ($single_list as $value) {
		if (!preg_match('/^.*\.(jpg|jpeg|png|gif|JPG|JPEG|GIF|pdf|PDF|wrd|wrdx|mp3)$/i', $value)) {

			//Checking of the link found starts with either http or / in which case we fix the url to absolute
			$a = strpos($value, 'http', 0);
			$b = strpos($value, '/', 0);
			$c = strpos($value, '../', 0);
			if ($a === 0) {
				$tvalue = $value;
			} elseif ($b === 0) {
				$tvalue = $this->_domain . $value;
			} elseif ($c === 0) {
				$tvalue = $this->_domain;
			} else {
				$tvalue = $this->_domain . '/' . $value;
			}	
			if (!in_array($tvalue, $_SESSION['page_list'])) {
				 if (!in_array($tvalue, $_SESSION['page_list_done']) && $tvalue != @$_SESSION['next_page']) {
					array_push($_SESSION['page_list'], $tvalue);
				}
			}		
		}
	}
  }

	/**
	*Find all emails addresses from a href - $this->_html to be used to parse tags
	*/ 
	public function arrayfromanchors() {
		$this->_array_fanchors = array();
		foreach($this->_html->find('a') as $link) {
			$href = $link->href;
			if (preg_match('/^(mailto)/', $href)) {
				
				$temp = preg_split('/\?/', $href, NULL);
				$tempe = preg_replace('/\?/', '', $temp);
				$this->_array_fanchors[] = preg_replace('/mailto:/', '', $tempe[0]);

			}
		}
		$_SESSION['mail_list'] = array_merge($_SESSION['mail_list'], $this->_array_fanchors);
		$session = array();
		$session = array_unique(flattenn($_SESSION['mail_list']));
		//foreach ($session as $value) {
		//echo $value . '<br />';
		//}
		//var_dump(array_unique($_SESSION['mail_list']));
		echo '<br />';
	}


	/**
	*Find all email addresses from regex - $this->_html to be used to parse text
	*/

	public function arrayfromtext() {

		$pattern = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/'; //regex for pattern of e-mail address
		preg_match_all($pattern, $this->_html2, $matches); //find matching pattern
		$result = array_merge($_SESSION['mail_list'], $matches[0]);
		$_SESSION['mail_list'] = $result;
		echo '<br />';
		$session = array();
		$session = array_unique($_SESSION['mail_list']);
		foreach ($session as $value) {
		echo $value . '<br />';
		}
		//var_dump(array_unique($_SESSION['mail_list']));
		echo '<br />';

	}

	/**
	*Find data based on tags
	*/
	public function arrayfromtags() {
		$item = array();
		// Find all article blocks
		foreach($this->_html->find('div#col_a') as $article) {
			$item['name'] = $article->find('strong', 0)->plaintext;
			$item['ville'] = $article->find('li.ville', 0)->plaintext;
			$item['adresse'] = $article->find('li.adresse', 0)->plaintext;
			$item['next'] = "next";
		}
		$articles[] = $item;
		//print_r($articles);
		foreach ($articles as $value) {
			array_push($_SESSION['mail_list'], $value);
		}
		
	}

	/**
	*Function heading to next page and saving next page var
	*/
	public function header_next() {
		if (!empty($_SESSION['page_list'])) {
			$_SESSION['next_page'] = $_SESSION['page_list'][0];
			$_SESSION['page_list_done'][] = $_SESSION['page_list'][0];
			array_splice($_SESSION['page_list'], 0, 1);
			foreach ($_SESSION['page_list_done'] as $value) {
				echo $value . '<br />';
			}
			$_SESSION['started'] = 1;
			//var_dump($_SESSION['page_list']);
			echo '<br />';
			echo '<br />';
			echo '<br />';
			//var_dump($_SESSION['page_list_done']);
			header( "refresh:1;url=" . $_SERVER['PHP_SELF'] ); 
			//exit;
		} else if (empty($_SESSION['page_list']) && $_SESSION['started'] == 0) {
			$_SESSION['started'] = 1;
			echo 'oops session["started"] est egal à zero alors que page_list n\'est aps vide';
		} else if (empty($_SESSION['page_list']) && $_SESSION['started'] == 1) {
			echo "The domain has been parsed entirely ! <br />The e-mail list is below :";
			exit;
			
		}
	}

	/**
	*Function displaying output
	*/
	public function display() {
		foreach ($_SESSION['mail_list'] as $key => $value) {
			if ($key%2 == 1) {
				echo $value . '<br />';
			} else {
				foreach ($_SESSION['mail_list'][$key] as $k => $v) {
					echo $v . '<br />';
				}
			}
			echo '<br />';
		}
	}



}//Closing class

//Destroying session if needed
if (isset($_GET['des']) && $_GET['des'] == 'd') {
	session_destroy();
	echo 'Session détruite <br />';
	echo '<a href="http://127.0.0.1/parsing/email_crawler_final_session.php">retour</a>';
	exit;
}


/**
*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
*DO NOT ENTER A URL WITH a SLASH AT THE END, THIS WILL RESULT IN A MASSIVE FUCK UP IN REGARD OF RELATIVE URL
*VALID URL : http://www.domain.com
*INVALID URL : http://www.domain.com/
*INVALID URL : www.domaine.com
*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
*/

if (isset($_SESSION['started'])) {
	$tpages = array_merge($_SESSION['page_list'], $_SESSION['page_list_done']);
	$tcpage = count(array_unique($tpages));
	echo 'Pages totales : ' . $tcpage . '<br />';
	$cmail = count(array_unique(flattenn($_SESSION['mail_list'])));
	$cpage = count($_SESSION['page_list']);
	$cupage = count(array_unique($_SESSION['page_list']));
	$cpagedone = count($_SESSION['page_list_done']);
	$cupagedone = count(array_unique($_SESSION['page_list_done']));
	echo 'Pages scannées : ' . $cpagedone . '.<br />Pages restantes : ' . $cpage . '.<br />Emails trouvés : ' . $cmail . '.<br />';
	echo 'Pages scannées uniques : ' . $cupagedone . '.<br />Pages restantes uniques : ' . $cupage . '.<br /><br />';
}

$parse = new email_parser('http://maison-retraite.ehpadhospiconseil.fr');
//$parse->display();
$parse->checkhttp();
$parse->init();
$parse->arrayfromanchors();
$parse->arrayfromtags();
$parse->links_listing();
$parse->header_next();
/**
* Purging session vars (has to be done only when pretty much all pages have been saved listed 
*because w'ell then need obviously to comment the function links_listing())
*$_SESSION['purge'] = array();
*$_SESSION['mail_list'] = array();
*$_SESSION['page_list_done'] = array();
*/
$parse->purge();
echo '<br />';


echo '<a href="' . $_SERVER['PHP_SELF'] . '?des=d">session destroy</a><br />';
echo '<br /><br />';

$session = array();
$session = flattenn($_SESSION['mail_list']);
foreach ($session as $value) {
	echo $value . '<br />';
}


echo '</body>';

?>