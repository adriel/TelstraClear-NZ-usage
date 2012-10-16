<?php
require_once('settings.php'); // Load the settings.

// https://www.telstraclear.co.nz/customer-zone/internet-usage-meters/usagemeter/index.cfm?s=t&p=usagesummary&display_service=1&service=OnNet&next_bill_date=20120627000000
// Advanced settings
$loginData['coockie_location']	= 'cookie.txt';
$loginData['url'] 				= 'https://www.telstraclear.co.nz/amserver/UI/Login';
$loginData['ref_url'] 			= 'https://www.telstraclear.co.nz/selfservice-customerzone/login.jsf';
$loginData['usage_url'] 		= 'https://www.telstraclear.co.nz/selfservice-customerzone/secure/myprofile.jsf?tab=usage';
$loginData['user_agent'] 		= 'Mozilla/6.0 (Macintosh; Intel Mac OS X 10_8_1) AppleWebKit/700.0 (KHTML, like Gecko) Chrome/20.0.0.0 Safari/700.0';
$loginData['post_fields']	 	= 'IDToken1='.$username.'&IDToken2='.$password.'&encoded=false&gx_charset=UTF-8&failUrl=https://www.telstraclear.co.nz/selfservice-customerzone/login.jsf&goto=https://www.telstraclear.co.nz/selfservice-customerzone/secure/myprofile.jsf&realm=tclcustomers&service=customer&x=22&y=8';

// Step 1 - Login (only do this when it's needed - seems TelstraClear logs you out after about 30 mins)
// $loginStatus = tcLogin();
// print_r($loginStatus);

// Step 2 - Get usage array
$usageArr = getUsageArray( $loginData['usage_url'] );
print_r($usageArr);

// TODO: add date format option to func <--

// scrap TelstraClear usage page and output a usfull array
// htmlSource	= html source to output to an array

// returns 2 dimensional 'array' with
// 
// 		['status']			= 1 ok 0 error
// 		['message']			= message of what happened
// 		['data or error']	= returns the below array else and error message
//			['account_no']		= TelstraClear account no.
//			['account_type']	= TelstraClear account type
//			['cyle_start_date']	= date internet cycle starts (normally monthly)
//			['cyle_end_date']	= date internet cycle end	 (normally monthly)
//			['total_MB']		= size of cap in MB
//			['used_MB']			= amount of cap already used in MB
function getUsageArray($url)
{
	function getInitialUsageData($url)
	{
		// Step 2 - Load usage data
		// $usageSource = get_page($loginData['usage_url'],$loginData['user_agent'],$loginData['coockie_location']);
		$usageSource = get_page($url);

		// PARSE - (0) Account number
		$accountNo = getTagContents( $usageSource, 'span', 'class', 'mpUsageHdr', 'getTagCont' );
		$accountNo = trim(str_ireplace('Account ', '', $accountNo[0]));
	
		$usageData['account_no'] = $accountNo;
		$usageData['usage_source'] = $usageSource;
		return $usageData;
		
	}
	
	//  Get initial usage data - if this fails try logged in and then getting usage data again, then fail. 
	$initialUsageData = getInitialUsageData($url);
	
	// Return error if data was not found. You may need to run the login function ('curlGrabPage') before using the 'get_page' function. (try loggin in)
	if (empty($initialUsageData['account_no'])) {

		// Try login, if that fails then report that.
		if (tcLogin()) { // Logging in fixed the error - continue

			// If login was successful then try loading the initial usage data again and assign the account no. to the array and continue
			$initialUsageData = getInitialUsageData($url);
			$usageData['account_no'] = $initialUsageData['account_no']; // Add account no. to array that gets returned at the end of this function
			if (empty($usageData['account_no'])) {
			
				$loginStatus['status'] 	= false;
				$loginStatus['message']	= '01 - Failed to get initial usage data, even after a reported, successful login.';
				$loginStatus['error']	= 'Unknown';
				return $loginStatus;
				
			}
		}else { // loggin in did NOT fix the error - report and stop
			
			$loginStatus['status'] 	= false;
			$loginStatus['message']	= '02 - Failed to fetch usage data, tried to login, but this failed.';
			$loginStatus['error']	= 'Unknown';
			return $loginStatus;
		
		}
		
	}else { // if $initialUsageData['account_no'] had an account no. then continue.
		$usageData['account_no'] = $initialUsageData['account_no'];
	}
	// echo 'Account number: ' . $accountNo . "\n\n";

	// PARSE - (1) HighSpeed Cable - usage link
	$usageLink = getTagContents( $initialUsageData['usage_source'], 'a', 'class', 'mpUsage', 'getAttrCont', 'href' );
	// echo 'Usage link: ' . $usageLink[0] . "\n\n";

	// GET
	$usageHistorySrc = get_page($usageLink[0]);

	// PARSE - (2)
	preg_match_all("/serviceUsage\[0\]\ \=\ \"(.+)\"/", $usageHistorySrc, $currentBilling, PREG_SET_ORDER);
	// $currentBilling = getTagContents( $usageHistorySrc, 'a', 'class', 'usg_menuCurrent', 'getAttrCont', 'href' );
	if (empty($currentBilling[0][1])) {echo '03 - Failed to get billing page';return false;} // Return error if billing page failed to load
	// echo 'Current Billing link: https://www.telstraclear.co.nz' . $currentBilling[0] . "\n\n";

	// GET
	$tmpURL2 = 'https://www.telstraclear.co.nz' . $currentBilling[0][1];
	$usageDetailedSrc = get_page($tmpURL2);

	// PARSE - (3) Date info
	$currentBilling	['dates'] 			= getTagContents( $usageDetailedSrc, 'td', 'class', 'usg_content_hdr_txt', 'getTagCont', '', '/b' );
	$usageData		['cyle_start_date'] = trim(str_ireplace(array ('Usage Summary Graph: ',' - Today'), '', $currentBilling['dates'][0]));
	$usageData		['cyle_end_date'] 	= trim($currentBilling['dates'][1]);

	// PARSE - (4) Plan info
	$currentBilling	['plan'] 			= arrayRemoveEmpty( getTagContents( $usageDetailedSrc, 'td', 'class', 'usg_hdrTxtBig', 'getTagCont' ) );
	$usageData		['account_type'] 	= trim($currentBilling['plan'][0]);
	$usageData		['plan'] 			= trim($currentBilling['plan'][1]);
	$usageData		['total_GB'] 		= (int)trim(str_ireplace('LightSpeed ', '', $currentBilling['plan'][1]));

	// PARSE - (5) Usage info (cap)
	$currentBilling	['usage'] 			= getTagContents( $usageDetailedSrc, 'div', 'id', 'usg_content_info', 'getTagCont', '', '/strong' );

	$usageData		['used_GB'] 		= (float)trim($currentBilling['usage'][0]);
	$usageData		['left_GB'] 		= (float)trim($currentBilling['usage'][2]);
	$usageData		['percent_used'] 	= (int)trim($currentBilling['usage'][1]);
	// print_r($currentBilling['usage']);

	// check if we have data, if not then it's probably because we arn't logged in
	if (!empty($usageData['account_no'])) {
		
		$loginStatus['status'] 	= true;
		$loginStatus['message']	= 'Usage data found';
		$loginStatus['data']	= $usageData;
		
	}else {
		
		$loginStatus['status'] 	= false;
		$loginStatus['message']	= '04 - Failed to get usage data';
		$loginStatus['error']	= 'Unknown';
		// $loginStatus['source']	= $source; // give html source that was returned
		
	}
	// 		['status']			= 1 ok 0 error
	// 		['message']			= message of what happened
	// 		['data or error']	= returns the below array else and error message
	//			['account_no']		= TelstraClear account no.
	//			['account_type']	= TelstraClear account type
	//			['cyle_start_date']	= date internet cycle starts (normally monthly)
	//			['cyle_end_date']	= date internet cycle end	 (normally monthly)
	//			['total_GB']		= size of cap in GB
	//			['used_GB']			= amount of cap already used in GB
	//			['left_GB']			= amount of cap left in GB
	//			['percent_used']	= amount of cap already used in GB
	// Return usage array
	return $loginStatus;
}
function tcLogin()
{
	global $loginData;
	return curlGrabPage( $loginData['url'],$loginData['ref_url'],$loginData['post_fields'],$loginData['user_agent'],$loginData['coockie_location'] );
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

		if (!$outputAttr) {return '06 - No "$outputAttr" given, while $outputType was set to getAttrCont';} // Give error if $outputAttr is missing

	    $filtered = $domxpath->query("//$tagName" . '[@' . $attrName . "='$attrValue']/@" . $outputAttr);
		$output[0] = $filtered->item(0)->nodeValue."\n\n";
	
	}else {
		$output[0] = '07 - No "$outputType" given';
	}
	// return trim($output);
	return $output;
}


// Step 3 - Parse html code into a usable array
// $data_arr = htmlToArray($usageSource);
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
function curlGrabPage($url,$ref_url,$curl_data,$user_agent,$cookie_loc){
	// create blank cookie file if non found
	if(!file_exists($cookie_loc)) {
        $fp = fopen($cookie_loc, "w");
        fclose($fp);
    }

	$ch = curl_init();
	// 
	// Post login info to TelstraClear usage URL and get usage page source 
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
	ob_start();      // prevent any output
	$source = curl_exec($ch);
	ob_end_clean();  // stop preventing output
	// echo $source;
	// close cURL resource, and free up system resources
	curl_close($ch);
	
	
	if (strstr($source, 'Account Name:')) {
		
		$loginStatus['status'] 	= true;
		$loginStatus['message']	= 'Logged in';
		
	}else {
		
		$loginStatus['status'] 	= false;
		$loginStatus['message']	= '08 - Failed to login';
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
// 		raw html source code,
function get_page($url,$user_agent=false,$cookie_loc=false)
{
	// If no user agent not given, then use default user agent + cookie_loc
	if (!$user_agent) {
		global $loginData;
		$user_agent = $loginData['user_agent'];
		$cookie_loc = $loginData['coockie_location'];
	}
	
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

function arrayRemoveEmpty($arr){
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