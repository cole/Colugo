# Colugo #
Created by: evansims  
Contributors: cole  
Tags: colugo, twitter, image, publishing, self-hosted  

Colugo is a simple PHP script that allows you to host your tweeted images using Twitter for iPhone/iPad on your own web server.

## Description ##
Colugo is a simple PHP script that allows you to host your tweeted images using Twitter for iPhone/iPad or Twitterrific on your own web server. It writes author (your @username), date and copyright details onto the image. It can plug into [Shaun Inman's Lessn](http://shauninman.com/archive/2009/08/17/less_n "Lessn URL shortener") to automatically shorten the tweeted link to your image.

Colugo includes [Jason Kottke's Silkscreen font]( http://kottke.org/plus/type/silkscreen/ "Silkscreen font") for the text overlay. 

## Requirements ##
Check your web host to determine if they meet these requirements.

* 	Web server with FTP and PHP priviledges.
* 	Host that supports HTTP file_get_contents() if you intend on using Colugo with Lessn.
* 	Host must have GD, cURL, and JSON PHP modules enabled.

## Installation ##
1. 	Create a new directory on your web server. CHMOD that directory with appropriate write permissions (0755 usually works.)

2.	Extract the zip archive and open "u.php" in your preferred plain-text editor.

3. 	Assign the configuration values at the top of the script.  
	*$localBaseURL*  
   	Should point to the absolute URL where your images are being stored. i.e., if your u.php script is in /i, it might be http://yourdomain.com/i/.  Be sure to include the last slash.
	
   	*$twitterUsername*  
   	Your Twitter username. The script does not use this for authentication, but only this Twitter id is allowed to post to the service.
	
   	*$licenseText*  
   	If you'd like license information to be written onto the image, provide it here.  

	*$dateFormat*  
	If you'd like to format the date on your images, change it here.  [PHP date format strings](http://php.net/manual/en/function.date.php) are used.  
	
   	*$lessnEndpoint*  
   	Optional. If you have [Lessn](http://shauninman.com/archive/2009/08/17/less_n "Lessn URL shortener") installed, provide the URL path to the admin interface, i.e. http://yourdomain.com/g/-/.  Be sure to include the last slash.
	
   	*$lessnAPI*
   	Optional. If you have [Lessn](http://shauninman.com/archive/2009/08/17/less_n "Lessn URL shortener") installed, provide your API key. The API key can be found after you've logged into your admin interface, at the bottom of the page (it's in light grey, so you might not notice it  at first.)

4. 	Upload your modified u.php and the remainder of the contents of the archive to the directory you created.

## Configure Twitter Application ##

### Twitter for iPhone ###
1. 	Go to Twitter's accounts listing interface, and tap "Settings" in the lower, left hand corner.
2. 	Tap Services, then Image Service, and select Custom.
3. 	Change the URL to point to where you uploaded the "u.php" file; i.e. http://yourdomain.com/i/u.php.

### Twitter for iPad ###
1. 	Tap the settings icon in the lower middle of the screen.
2. 	Tap Services, then Image Service, and select Custom.
3. 	Change the URL to point to where you uploaded the "u.php" file; i.e. http://yourdomain.com/i/u.php.

### Twitterrific for iPad ###
1.	Tap the compose icon.
2.	Tap the camera icon, then Change Upload Service.
3.	Choose Other and change the URL to point to where you uploaded the "u.php" file; i.e. http://yourdomain.com/i/u.php.

