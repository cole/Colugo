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
    define('USER_AGENT', 'Colugo 0.2');

    ob_start();

    if (DEBUG_MODE) 
    {
        error_reporting(E_ALL); 
        ini_set('display_errors', 1);
    }

    // Variables sent by Consumer (hopefully)
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $username = isset($_POST['username']) ? $_POST['username'] : '';

    // check various requirements upfront where possible
    if (!(extension_loaded('curl') && function_exists('curl_init')))
    {
        SendResponse('Curl PHP extension required.',500);
    }

    if (!(extension_loaded('json') && function_exists('json_decode')))
    {
        SendResponse('JSON PHP extension required.',500);
    } 

    if (!(extension_loaded('gd') && function_exists('gd_info'))) 
    {
        SendResponse('GD PHP extension required.',500);
    }

    if ($_SERVER['REQUEST_METHOD'] != 'POST') 
    {
        SendResponse('This service requires a POST request.',400);
    }

    if (!isset($_FILES['media'])) 
    {
        SendResponse('There was an error uploading the file.',400);
    }
    // Request looks OK so far


    // Authenticate with twitter via OAuth Echo
    // This will exit if authentication fails
    $oAuth = OAuthEcho($_SERVER['HTTP_X_VERIFY_CREDENTIALS_AUTHORIZATION'],$_SERVER['HTTP_X_AUTH_SERVICE_PROVIDER']);

    // Decode twitter screen name from JSON response, and use it if present
    if (isset($oAuth['response']['screen_name'])) {
        $username = $oAuth['response']['screen_name'];
    }

    // Error if username doesn't match
    if ($username != $twitterUsername) 
    {
        SendResponse('That user is not allowed to post images to this service.',401);
    }

    // Error if authentication didn't work, and provide info from twitter
    if (!$oAuth['httpcode'] == 200) 
    {
        SendResponse($oAuth['response']['error'],$oAuth['httpcode']);
    }

    $file = time();

    switch($_FILES['media']['type']) 
    {
        case "image/jpeg":
            $file = $file . '.jpg';
            // Image processing
            ProcessImage($_FILES['media']['tmp_name'], $message, $licenseText, $username, $dateFormat);
            break;
        case "video/mp4":
            $file = $file . '.mp4';
            break;
        default:
            SendResponse('Unknown file type.',500);
    }

    // Move file into place
    if (@move_uploaded_file($_FILES['media']['tmp_name'], $file))
    {
        @chmod($file, 0777);
    } 
    else 
    {
        SendResponse('Unable to move file into place.',500);
    }

    $url = urlencode("{$localBaseURL}{$file}");

    // Shorten the URL with Lessn if configured
    if ($lessnAPI && $lessnEndpoint) 
    {
        $url = file_get_contents("{$lessnEndpoint}?url={$url}&api={$lessnAPI}");
    }

    SendResponse("<mediaurl>{$url}</mediaurl>", 200, array('Content-Type' => 'text/xml'));

    function OAuthEcho($credentials, $url = 'https://api.twitter.com/1/account/verify_credentials.json')
    {
        if ($credentials == '')
        {
            SendResponse('OAuth headers are required for authorization.',401);
        }

        $oAuth = array();
        $oAuth['url'] = $url;
        $oAuth['request'] = $credentials;
        $oAuth['httpcode'] = 0;
        $oAuth['httpinfo'] = '';
        $oAuth['raw'] = '';

        // Use curl to request authentication through OAuth Echo 
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $credentials,
          ));

        $oAuth['raw'] = curl_exec($curl);
        $oAuth['httpcode'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $oAuth['httpinfo'] = curl_getinfo($curl);

        // Handle curl errors here
        if (curl_errno($curl)) 
        {
            SendResponse('Curl error: ' . curl_error($curl),500);
        }

        curl_close($curl);

        $oAuth['response'] = json_decode($oAuth['raw'], true);

        return $oAuth;
    }

    function SendResponse($response, $statusCode = 200, $extraHeaders = array('Content-Type' => 'text/plain')) 
    {        
        if (DEBUG_MODE) 
        {
            global $_POST, $_GET, $_FILES, $_SERVER, $oAuth;

            $out['post'] = $_POST;
            $out['get'] = $_GET;
            $out['files'] = $_FILES;
            $out['oauthcred'] = $oAuth['request'];
            $out['oauthresponse'] = $oAuth['raw'];
            $out['oauthstatus'] = $oAuth['httpcode'];

            $out['status'] = $response;

            ob_start();
            var_dump($out);
            $out = ob_get_clean();

            file_put_contents('dump.txt', $out);
            chmod('dump.txt', 0777);
        }

        if (!headers_sent()) 
        {
            header($_SERVER['SERVER_PROTOCOL'], true, $statusCode);
            header('Status: ' . $statusCode, true);
            foreach ($extraHeaders as $header => $value) 
            {
                header($header . ': ' . $value, true);
            }
        }

        echo $response;

        ob_end_flush();

        exit();

    }

    function ProcessImage($image, $message, $licenseText, $username, $dateFormat) 
    {    
        $imgOriginal = imagecreatefromjpeg($image);
        $ox = imagesx($imgOriginal);
        $oy = imagesy($imgOriginal);

        if (file_exists("./watermark.png")) 
        {

            $imgOriginal = imagecreatefromjpeg($image);
            $imgWatermark = imagecreatefrompng('./watermark.png');

            $merge_right = 10;
            $merge_bottom = 10;
            $sx = imagesx($imgWatermark);
            $sy = imagesy($imgWatermark);

            imagecopy($imgOriginal, $imgWatermark, $ox - $sx - $merge_right, $oy - $sy - $merge_bottom, 0, 0, $sx, $sy);
            imagejpeg($imgOriginal, $image, 85);
        } 
        else 
        {
            $colorText = imagecolorallocatealpha($imgOriginal, 255, 255, 255, 63.5);
            $colorShadow = imagecolorallocatealpha($imgOriginal, 0, 0, 0, 63.5);

            $text = '';
            if (strlen($message)) $text = wordwrap($message) . "\n";
            if (strlen($licenseText)) $licenseText = "   {$licenseText}";
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
?>