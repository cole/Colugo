<?php

    // Colugo 0.2
    // Original by Evansims

	/* Configuration Variables */

	$localBaseURL = 'http://mysite.com/photos/'; // Your URL, with a trailing slash but NO u.php.  E.g. http://mysite.com/photos/
	$twitterUsername = ''; // The service will be restricted to this Twitter account

	$licenseText = ""; // Optional text to print on the image
	$dateFormat = "Y-m-d H:i O"; // PHP format for date on image; see http://php.net/manual/en/function.date.php

	$lessnEndpoint  = '';  // Optional Lessn URL shortener endpoint, e.g. http://mysite.com/lessn/-/
	$lessnAPI = ''; // Optional Lessn URL shortener API key

	/* Do not modify below this line */
	
	define('DEBUG_MODE', false);
	
	ob_start();
	
	if(DEBUG_MODE) {
	    error_reporting(E_ALL); 
        ini_set('display_errors', 1);
	}

	$userAgent = 'Colugo 0.2';
	
	if($_SERVER['REQUEST_METHOD'] == 'POST') {

        // Variables sent by Consumer (hopefully)
		$source = isset($_POST['source']) ? $_POST['source'] : '';
		$message = isset($_POST['message']) ? $_POST['message'] : '';
		$username = '';
		$media = '';
	    
		$response = '';
		
		if(extension_loaded('curl') && function_exists('curl_init')) {
            
            // OAuth Echo variables
            $oAuthVerificationUrl = isset($_SERVER['HTTP_X_AUTH_SERVICE_PROVIDER']) ? $_SERVER['HTTP_X_AUTH_SERVICE_PROVIDER'] : 'https://api.twitter.com/1/account/verify_credentials.json';
    		$oAuthCredentials = isset($_SERVER['HTTP_X_VERIFY_CREDENTIALS_AUTHORIZATION']) ? $_SERVER['HTTP_X_VERIFY_CREDENTIALS_AUTHORIZATION'] : '';
    		$oAuthResultHttpCode = 0;
    		$oAuthResultHttpInfo = '';
            $oAuthResponseText = '';
            $oAuthResponse = array();
    	    
    		// Use curl to request authentication through OAuth Echo 
    		$curl = curl_init($oAuthVerificationUrl);
            curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Authorization: ' . $oAuthCredentials,
              ));

            $oAuthResponseText = curl_exec($curl);
            $oAuthResultHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $oAuthResultHttpInfo = curl_getinfo($curl);

            // Handle curl errors here
            if(curl_errno($curl))
            {
                SendResponse('Curl error: ' . curl_error($curl),500);
            }

            curl_close($curl);
            
    		if(extension_loaded('json') && function_exists('json_decode')) {
                $oAuthResponse = json_decode($oAuthResponseText, true);
            }
            else {
                SendResponse('JSON PHP extension required.',500);
            }
            
        }
        // Curl extension check failed
        else {
            SendResponse('Curl PHP extension required.',500);
        }
		
        // Decode twitter id from json response, and use it if present
        $username = isset($oAuthResponse['screen_name']) ? $oAuthResponse['screen_name'] : '';
        
        // Authentication worked
        if ($oAuthResultHttpCode == 200) {

          // Check that we have the right user
          if ($username == $twitterUsername) {
              if(isset($_FILES['media'])) {
      			$file = time();
      			$file = $file . '.jpg';
                
                // Image processing
      			if(@move_uploaded_file($_FILES['media']['tmp_name'], $file)) {
      				@chmod($file, 0777);

                    ProcessImage($file);

      				$url = "{$localBaseURL}{$file}";

      				if($lessnAPI && $lessnEndpoint) $url = LessnURL($url);
      				$response = "<mediaurl>{$url}</mediaurl>";
      				
      				SendResponse($response,200,array('Content-Type' => 'text/xml'));
      			} 
      			else {
      				SendResponse('There was an error uploading the file.',500);
      			}
      		}
              
          } 
          // User doesn't match
          else {
              SendResponse('That user is not allowed to post images to this service.',401);
          }

        } 
        // verification failed, we should return the error back to the consumer
        else {
          $response = isset($oAuthResponse['error']) ? $oAuthResponse['error'] : null;
          
          SendResponse($response,$oAuthResultHttpCode);
        }

	} 
	// Request was not a POST
	else {
	    SendResponse('This service requires a POST request.',400);
	}

    function SendResponse($response, $statusCode = 200, $extraHeaders = array('Content-Type' => 'text/plain')) {
        
        global $_POST, $_GET, $_FILES, $_SERVER, $oAuthCredentials, $oAuthResponseText, $oAuthResultHttpCode;
        
        if(DEBUG_MODE) {
    		$out['post'] = $_POST;
    		$out['get'] = $_GET;
    		$out['files'] = $_FILES;
    		$out['oauthcred'] = $oAuthCredentials;
    		$out['oauthresponse'] = $oAuthResponseText;
    		$out['oauthstatus'] = $oAuthResultHttpCode;
    		
    		$out['status'] = $response;

    		ob_start();
    		var_dump($out);
    		$out = ob_get_clean();

    		file_put_contents('dump.txt', $out);
    		chmod('dump.txt', 0777);
    	}
    	
    	if (!headers_sent()) {
    	    header($_SERVER['SERVER_PROTOCOL'], true, $statusCode);
    	    header('Status: ' . $statusCode, true);
    	    foreach ($extraHeaders as $header => $value) {
    	        header($header . ': ' . $value, true);
	        }
    	}
    	
    	echo $response;
    	
    	ob_end_flush();
    	
    	exit();
    	
    }

	function LessnURL($url) {

		global $lessnEndpoint, $lessnAPI;

		$url = urlencode($url);
		return file_get_contents("{$lessnEndpoint}?url={$url}&api={$lessnAPI}");
	}
	
	function ProcessImage($image) {
	    
	    global $message, $licenseText, $username, $dateFormat;
	
		if(extension_loaded('gd') && function_exists('gd_info')) {
			$imgOriginal = imagecreatefromjpeg($image);
			$ox = imagesx($imgOriginal);
			$oy = imagesy($imgOriginal);

			if(file_exists("./watermark.png")) {

				$imgOriginal = imagecreatefromjpeg($image);
				$imgWatermark = imagecreatefrompng('./watermark.png');

				$merge_right = 10;
				$merge_bottom = 10;
				$sx = imagesx($imgWatermark);
				$sy = imagesy($imgWatermark);

				imagecopy($imgOriginal, $imgWatermark, $ox - $sx - $merge_right, $oy - $sy - $merge_bottom, 0, 0, $sx, $sy);
				imagejpeg($imgOriginal, $image, 85);

			} 
			else {

				$colorText = imagecolorallocatealpha($imgOriginal, 255, 255, 255, 63.5);
				$colorShadow = imagecolorallocatealpha($imgOriginal, 0, 0, 0, 63.5);

				$text = '';
				if(strlen($message)) $text = wordwrap($message) . "\n";
				if(strlen($licenseText)) $licenseText = "   {$licenseText}";
				$dated = date($dateFormat);
				$text .= "@{$username}   {$dated}{$licenseText}";

				$box = imagettfbbox(6, 0, "./silkscreen.ttf", $text);
				$boxHeight = $box[3] - $box[5];
				$bX = 10;
				$bY = $oy - $boxHeight - 6;
				imagettftext($imgOriginal, 6, 0, $bX + 1, $bY + 1, $colorShadow, "./silkscreen.ttf", $text);
				imagettftext($imgOriginal, 6, 0, $bX, $bY, $colorText, "./silkscreen.ttf", $text);

				imagejpeg($imgOriginal, $image, 85);

			}
		}
		else {
		    SendResponse('GD PHP extension required.',500);
		}
	}
?>