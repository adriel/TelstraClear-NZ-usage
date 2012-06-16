<?php
require_once('settings.php'); // Load the settings.

// https://www.telstraclear.co.nz/customer-zone/internet-usage-meters/usagemeter/index.cfm?s=t&p=usagesummary&display_service=1&service=OnNet&next_bill_date=20120627000000
// Advanced settings
$loginData['coockie_location']	= 'cookie.txt';
$loginData['url'] 				= 'https://www.telstraclear.co.nz/amserver/UI/Login';
$loginData['ref_url'] 			= 'https://www.telstraclear.co.nz/selfservice-customerzone/login.jsf';
$loginData['usage_url'] 		= 'https://www.telstraclear.co.nz/selfservice-customerzone/secure/myprofile.jsf?tab=usage';
$loginData['user_agent'] 		= 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_2) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/15.0.874.121 Safari/535.2';
$loginData['post_fields']	 	= 'IDToken1='.$username.'&IDToken2='.$password.'&encoded=false&gx_charset=UTF-8&failUrl=https://www.telstraclear.co.nz/selfservice-customerzone/login.jsf&goto=https://www.telstraclear.co.nz/selfservice-customerzone/secure/myprofile.jsf&realm=tclcustomers&service=customer&x=22&y=8';

// Step 1 - Login (only do this when it's needed - seems TelstraClear logs you out after about 30 mins)
// print_r( curl_grab_page($loginData['url'],$loginData['ref_url'],$loginData['post_fields'],$loginData['user_agent'],$loginData['coockie_location']) );

// Step 2 - Get usage array
// print_r($get_usage_array);

function get_usage_array($value='')
{
	# code...
}
// Step 2 - Load usage data
$usageSource = get_page($loginData['usage_url'],$loginData['user_agent'],$loginData['coockie_location']);


// PARSE -(0) Account number
$accountNo = getTagContents( $usageSource, 'span', 'class', 'mpUsageHdr', 'getTagCont' );
$accountNo = trim(str_ireplace('Account ', '', $accountNo[0]));
if (empty($accountNo)) {echo 'Failed to fetch usage data.'; return 0;} // Return error if data was not found. You may need to run the login function ('curl_grab_page') before using the 'get_page' function
// echo 'Account number: ' . $accountNo . "\n\n";

// PARSE - (1) HighSpeed Cable - usage link
$usageLink = getTagContents( $usageSource, 'a', 'class', 'mpUsage', 'getAttrCont', 'href' );
// echo 'Usage link: ' . $usageLink[0] . "\n\n";

// GET
$usageHistorySrc = get_page($usageLink[0],$loginData['user_agent'],$loginData['coockie_location']);

// PARSE - (2)
$currentBilling = getTagContents( $usageHistorySrc, 'a', 'class', 'usg_menuCurrent', 'getAttrCont', 'href' );
if (empty($currentBilling[0])) {echo 'Failed to get billing page';return 0;} // Return error if billing page failed to load
// echo 'Current Billing link: https://www.telstraclear.co.nz' . $currentBilling[0] . "\n\n";

// GET
$tmpURL2 = 'https://www.telstraclear.co.nz' . $currentBilling[0];
$usageDetailedSrc = get_page($tmpURL2,$loginData['user_agent'],$loginData['coockie_location']);

// PARSE - (3) Date info
$currentBilling	['dates'] 		= getTagContents( $usageDetailedSrc, 'td', 'class', 'usg_content_hdr_txt', 'getTagCont', '', '/b' );
$usageData		['dateStart'] 	= trim(str_ireplace(array ('Usage Summary Graph: ',' - Today'), '', $currentBilling['dates'][0]));
$usageData		['dateEnd'] 	= trim($currentBilling['dates'][1]);

// PARSE - (4) Plan info
$currentBilling	['plan'] 		= array_remove_empty(getTagContents( $usageDetailedSrc, 'td', 'class', 'usg_hdrTxtBig', 'getTagCont' ));
$usageData		['planType'] 	= trim($currentBilling['plan'][0]);
$usageData		['plan'] 		= trim($currentBilling['plan'][1]);
$usageData		['capGB'] 		= (int)trim(str_ireplace('LightSpeed ', '', $currentBilling['plan'][1]));

// PARSE - (5) Usage info (cap)
$currentBilling	['usage'] 		= getTagContents( $usageDetailedSrc, 'div', 'id', 'usg_content_info', 'getTagCont', '', '/strong' );

$usageData		['dataUsedGB'] 	= (float)trim($currentBilling['usage'][0]);
$usageData		['dataLeftGB'] 	= (float)trim($currentBilling['usage'][2]);
$usageData		['dataPrcntUsed'] = (int)trim($currentBilling['usage'][1]);
// print_r($currentBilling['usage']);



#
#
	print_r($usageData);
#
#

// // $some_link = 'some website';
// $tagName = 'span';
// $attrName = 'class';
// $attrValue = 'mpUsageHdr';

// $dom = new DOMDocument;
// $dom->preserveWhiteSpace = false;
// // @$dom->loadHTMLFile($some_link);
// @$dom->loadHTML($usageSource);

// $html = getTagsDebug( $dom, $tagName, $attrName, $attrValue );
// echo $html;
function getTagsDebug( $dom, $tagName, $attrName, $attrValue ){
    $html = '';
    $domxpath = new DOMXPath($dom);
    $newDom = new DOMDocument;
    $newDom->formatOutput = true;

    $filtered = $domxpath->query("//$tagName" . '[@' . $attrName . "='$attrValue']");
    // $filtered =  $domxpath->query('//div[@class="className"]');
    // '//' when you don't know 'absolute' path
	
	echo $filtered->item(0)->nodeValue."\n\n";
	
    // since above returns DomNodeList Object
    // I use following routine to convert it to string(html); copied it from someone's post in this site. Thank you.
    $i = 0;
    while( $myItem = $filtered->item($i++) ){
        $node = $newDom->importNode( $myItem, true );    // import node
        $newDom->appendChild($node);                    // append node
    }
    $html = $newDom->saveHTML();
    return $html;
}
function getTagContents( $usageSource, $tagName, $attrName, $attrValue, $outputType, $outputAttr=null, $extraVal=null ){

	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = false;
	@$dom->loadHTML($usageSource);

    $html = '';
    $domxpath = new DOMXPath($dom);
    $newDom = new DOMDocument;
    $newDom->formatOutput = true;

	
	if ($outputType == 'getTagCont') {
	    
	$filtered = $domxpath->query("//$tagName" . '[@' . $attrName . "='$attrValue']" . $extraVal);
	    // $filtered =  $domxpath->query('//div[@class="className"]');
	    // '//' when you don't know 'absolute' path
		// $output = $filtered->item(1)->nodeValue."\n\n";
		
		for ($i=0; $i < $filtered->length; $i++) { 
			$output[$i] = $filtered->item($i)->nodeValue;			
		}
		
	}elseif ($outputType == 'getAttrCont') {

		if (!$outputAttr) {return 'No "$outputAttr" given, while $outputType was set to getAttrCont';} // Give error if $outputAttr is missing

	    $filtered = $domxpath->query("//$tagName" . '[@' . $attrName . "='$attrValue']/@" . $outputAttr);
		$output[0] = $filtered->item(0)->nodeValue."\n\n";
	
	}else {
		$output[0] = 'No "$outputType" given';
	}
	// return trim($output);
	return $output;
}





// for ($i=0; $i < $tv3_title->length; $i++) { 
// 	// if($captions_or_HD->item($i)->nodeName == 'alt'){
// 		// echo "[". $captions_or_HD->item($i)->nodeName. "] ".$captions_or_HD->item($i)->nodeValue."\n";
// 		echo "[". $tv3_title->item($i)->nodeName. "] ".$tv3_title->item($i)->nodeValue."\n";
// 	// }
// }
// echo "\n".$title->item($i2)->nodeValue;





// Step 3 - Parse html code into a usable array
// $data_arr = html_to_array($usageSource);
// print_r( $data_arr );
// print_r( $data_arr['data'] ); // Just the data array



// $url 		= page to POST data
// $ref_url 	= tell the server which page you came from (spoofing)
// $curl_data 	= curl post field data
// $user_agent 	= user agent to send site
// $cookie_loc 	= cookie file location

// Load login page + store cookie -> login + store logged in state in cookie.
// returns 'array' with 
// 			['status']	1 = ok  0 = error
// 			['message']	status message (will display error is there is one)
// 			['error']	error message (will display what error you got if you got one)
function curl_grab_page($url,$ref_url,$curl_data,$user_agent,$cookie_loc){
	// create blank cookie file if non found
	if(!file_exists($cookie_loc)) {
        $fp = fopen($cookie_loc, "w");
        fclose($fp);
    }
	
	// 
	// First load up the login page and save the cookie
	// 
	
	$ch = curl_init();
	// 
	// // set URL and other appropriate options
	// $options = array( 
	// 	CURLOPT_URL				=> $ref_url,
	// 	CURLOPT_RETURNTRANSFER	=> false,			// return web page 
	// 	// CURLOPT_REFERER		=> true,			// follow redirects 
	// 	// CURLOPT_FOLLOWLOCATION	=> true,			// follow location 
	// 	CURLOPT_USERAGENT		=> $user_agent,     // who am i 
	// 	CURLOPT_COOKIEJAR		=> $cookie_loc,
	// 	CURLOPT_CONNECTTIMEOUT	=> 5,				// timeout on connect (default 120)
	// 	CURLOPT_TIMEOUT			=> 5,				// timeout on response (default 120)
	// 	CURLOPT_MAXREDIRS		=> 10,				// stop after 10 redirects 
	// 	// CURLOPT_AUTOREFERER	=> true,			// set referer on redirect 
	// ); 
	// 
	// curl_setopt_array($ch, $options);
	// 
	// // grab URL and pass it to the browser
	// ob_start();		// prevent any output
	// curl_exec($ch);	// execute the curl command
	// ob_end_clean();	// stop preventing output
	
	// sleep(2); // Give telecom a second to breath?
	
	// 
	// Post login info to telecom usage URL and get usage page source 
	// 
	
	// set URL and other appropriate options
	$options = array( 
		CURLOPT_URL				=> $url,
		CURLOPT_RETURNTRANSFER	=> true,			// return web page 
		CURLOPT_FOLLOWLOCATION	=> $ref_url,		// follow redirects 
		CURLOPT_REFERER			=> true,			// follow redirects 
		CURLOPT_FOLLOWLOCATION	=> true,			// follow location 
		CURLOPT_USERAGENT		=> $user_agent,     // who am i 
		CURLOPT_COOKIEJAR		=> $cookie_loc,		// Write to cookie file
		// CURLOPT_COOKIEFILE		=> $cookie_loc, // Read cookie file
		CURLOPT_CONNECTTIMEOUT	=> 5,				// timeout on connect (default 120)
		CURLOPT_TIMEOUT			=> 5,				// timeout on response (default 120)
		CURLOPT_MAXREDIRS		=> 10,				// stop after 10 redirects 
		// CURLOPT_AUTOREFERER	=> true,			// set referer on redirect 
		CURLOPT_POST			=> true,			// send post data 
		CURLOPT_POSTFIELDS		=> $curl_data		// post vars 
	); 
	
	curl_setopt_array($ch, $options);

	// grab URL and pass it to the browser
	// ob_start();      // prevent any output
	$source = curl_exec($ch);
	// ob_end_clean();  // stop preventing output
	echo $source;
	// close cURL resource, and free up system resources
	curl_close($ch);
	
	
	if (strstr($source, 'VIGN HPD cache address:')) {
		
		$loginStatus['status'] 	= 1;
		$loginStatus['message']	= 'Logged in';
		
	}elseif (strstr($source,'Maximum sessions limit reached or session quota has exhausted')) {
		
		$loginStatus['status'] 	= 0;
		$loginStatus['message']	= 'Error during login';
		$loginStatus['error']	= 'Maximum sessions limit reached or session quota has exhausted';
		
	}
	else {
		
		$loginStatus['status'] 	= 0;
		$loginStatus['message']	= 'Failed to login';
		$loginStatus['error']	= 'Unknown';
		// $loginStatus['source']	= $source; // give html source that was returned
		
	}
	return $loginStatus;

}

// Get source from a page
// url			= page to get source code 
// user_agent	= user agent to send to page
// cookie_loc	= location of cookie to use

// returns 'string' with
// 		raw html source code
function get_page($url,$user_agent,$cookie_loc)
{
	$ch = curl_init();

	// set URL and other appropriate options
	$options = array( 
		CURLOPT_URL				=> $url,			// URL to load
		CURLOPT_RETURNTRANSFER	=> true,			// return web page 
		CURLOPT_FOLLOWLOCATION	=> true,			// follow redirects 
		CURLOPT_USERAGENT		=> $user_agent,     // who am i 
		CURLOPT_COOKIEFILE		=> $cookie_loc,		// Read from cookie file
		CURLOPT_COOKIEJAR		=> $cookie_loc,		// Write to cookie file					- Added this here to update the cookie elsethe 2nd GET will be redirected to the main usage page (step 1)
		CURLOPT_CONNECTTIMEOUT	=> 5,				// timeout on connect (default 120)
		CURLOPT_TIMEOUT			=> 5,				// timeout on response (default 120)
		CURLOPT_MAXREDIRS		=> 10,				// stop after 10 redirects 
		// CURLOPT_AUTOREFERER	=> true,			// set referer on redirect 
	); 
	
	curl_setopt_array($ch, $options);

	ob_start();      // prevent any output
	return curl_exec($ch);
	ob_end_clean();  // stop preventing output

	// close cURL resource, and free up system resources
	curl_close($ch);

	// $err     = curl_errno($ch); 
	// $errmsg  = curl_error($ch) ; 
	// $header  = curl_getinfo($ch); 
	// curl_close($ch); 
	// 
	//  $header['errno']   = $err; 
	//  $header['errmsg']  = $errmsg; 
	//  $header['content'] = $content; 
	// return $header; 
}

// TODO: add date format option to func <--

// scrap Telecom usage page and output a usfull array
// htmlSource	= html source to output to an array

// returns 2 dimensional 'array' with
// 
// 		['status']			= 1 ok 0 error
// 		['message']			= message of what happened
// 		['data or error']	= returns the below array else and error message
//			['account_no']		= Telecom account no.
//			['account_type']	= Telecom account type
//			['cyle_start_date']	= date internet cycle starts (normally monthly)
//			['cyle_end_date']	= date internet cycle end	 (normally monthly)
//			['total_MB']		= size of cap in MB
//			['used_MB']			= amount of cap already used in MB
function html_to_array($htmlSource='')
{
	if ($htmlSource == '') {
		return false;
	}
	$doc = new DOMDocument();
	// added "@" at the start to silence any errors
	@$doc->loadHTML($htmlSource);

	$xpath = new DOMXpath($doc);

	// does awesomeness untill telecom decides to update their usage page *yeah right*
	$acc_no 						= $xpath->query('//span[@class="formText"]');
	$source_arr['account_no'] 		= trim($acc_no->item(0)->nodeValue);
	
	$table_one 						= $xpath->query('//table[@class="table"]/tr/td');
	$source_arr['account_type'] 	= trim($table_one->item(5)->nodeValue);
	list($start_date, $end_date) 	= explode("-", $table_one->item(3)->nodeValue);
	$source_arr['cyle_start_date'] 	= trim($start_date);
	$source_arr['cyle_end_date'] 	= trim($end_date);

	$source_cap_total 				= $xpath->query('//div[@class="usage1"]/font/b');
	$source_arr['total_MB'] 		= (int) trim($source_cap_total->item(0)->nodeValue);
	
	$source_used_MB 				= $xpath->query("//nobr");
	$source_used_MB 				= trim($source_used_MB->item(0)->nodeValue);
	$source_arr['used_MB'] 			= (float) str_replace(',', '', $source_used_MB);
	
	// check if we have data, if not then it's probably because we arn't logged in
	if ($source_arr['account_no'] != '') {
		
		$loginStatus['status'] 	= 1;
		$loginStatus['message']	= 'Usage data found';
		$loginStatus['data']	= $source_arr;
		
	}else {
		
		$loginStatus['status'] 	= 0;
		$loginStatus['message']	= 'Failed to get usage data';
		$loginStatus['error']	= 'Unknown';
		// $loginStatus['source']	= $source; // give html source that was returned
		
	}
	
	return $loginStatus;
	
	// // 
	// // debug xpath query
	// // note: make sure to comment out the return comment above 
	// foreach ($acc_no as $element) {
	// 	// echo "<br/>[$i-". $element->nodeName. "]";
	// 
	// 	// echo $channel_elements->item(0)->nodeValue;
	// 	// echo $channel_elementsdes;
	// 	$nodes = $element->childNodes;
	// 	foreach ($nodes as $node) {
	// 		// echo $node->nodeValue. "\n";
	// 		$channel_list_arr[] = trim($node->nodeValue);
	// 	}
	// }
	// print_r($channel_list_arr);	
}

function array_remove_empty($arr){
// http://www.jonasjohn.de/snippets/php/array-remove-empty.htm
// Used this one (much better/faster) https://hasin.wordpress.com/2009/09/16/removing-empty-elements-from-an-array-the-php-way/

	$empty_elements = array_keys($arr,"");
	foreach ($empty_elements as $e)
	unset($arr[$e]);

	// Reset numbering ([0],[2] --> [0],[1] etc)
	$i = 0;
	foreach ($arr as $key => $value) {
		$clean_arr_no[$i] = $value;
		$i++;		
	}
	unset($arr);
	
    return $clean_arr_no;
}
?>