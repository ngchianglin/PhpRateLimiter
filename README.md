# A simple finite state Php rate Limiter

## Introduction
The repository contains a simple Php script that does rate limiting for each IP address. It can be used to prevent excessive access to a web resource such as a form submission, a web page access, an api call etc... 

The script can also augment a web captcha. With machine learning and AI techniques improving rapidly, web captcha will become easier to defeat. Rate limiting can act as an additional layer of defense. 


## Structure and Usage
The throttle.php is the main rate limiting script. It uses a Mariadb database for storing counter records used for the throttling. 
db.sql contains the sql statements to create the necessary database structure. 

The rate limiting logic in throttle.php is implemented using a finite state machine. 
Two constants are defined in throttle.php to specify the rate limit

define("THROTTLE_INTERVAL", 60); //in seconds

define("INTERVAL_RATE", 5); //Number allowed in throttle interval. Eg. 5 emails per 60 seconds

The throttle-demo.php demonstrates how to integrate the throttling script into a php application. 
The state machine in throttle.php will call allow() or disallow() function depending on whether the rate is exceeded. 

Application scripts can include these two functions and place their own code in the function bodies. 

Warning, throttle-demo.php uses a HTTP GET parameter ip for testing and simulation purposes. The is no validation for this input and it is not effective for real throttling. The HTTP GEt parameter should be removed in production. 

Refer to the following for a detailed article on setting up and using this throttling script. 
[https://www.nighthour.sg/articles/2017/php-rate-limiter-finite-state.html](https://www.nighthour.sg/articles/2017/php-rate-limiter-finite-state.html)

## Testing

The java WebClient application (WebClient.java) included in the repository can be used to test the rate limiter by accessing the throttle-demo.php script. 


## Source signature
Gpg Signed commits are used for committing the source files. 

> Look at the repository commits tab for the verified label for each commit. 

> A userful link on how to verify gpg signature in [https://github.com/blog/2144-gpg-signature-verification](https://github.com/blog/2144-gpg-signature-verification)

