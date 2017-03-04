<?php

/*
* MIT License
*
* Copyright (c) 2017 Ng Chiang Lin
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*/

/*
throttle-demo.php 
Simple script to demo how to use 
the php rate limiter, throttle.php

Refer to the following link for detailed article on usage
https://www.nighthour.sg/articles/2017/php-rate-limiter-finite-state.html


Ng Chiang Lin
Feb 2017

*/


/* 
Includes the main throttling script with the finite state machine
Note in production, you may want to place throttle.php into a location outside 
of the documentroot, perhaps in a php library location
*/
require_once 'throttle.php';


/* 
This function is called by the state machine in throttle.php
when the throttling rate is within limit. 
The actual work that we want to do if it is within the 
rate limit can be placed inside this function.  
*/

function allow($ip, $result)
{
    echo "Allowed : " . $ip . " count: " . $result['count'] . "<br>\n";
    
}  


/*
This function is called by the state machine in throttle.php
when the throttling rate is exceeded.
Any work to be done if the rate limit is exceeded can be placed 
here. E.g. You can leave this empty and simply exit when the rate
limit is exceeded. 

*/
function disallow($ip, $result)
{
    echo "Disallowed : " .$ip . " count: " . $result['count'] .  "<br>\n";
    exit(1);
}



if( isset($_SERVER['REQUEST_METHOD'])  &&  strcasecmp("get", $_SERVER['REQUEST_METHOD'] ) == 0   )
{ //Check that it is a HTTP GET
  
    $ip = $_SERVER['REMOTE_ADDR']; //Connecting remote client ip address
    
    
    if(isset($_GET['ip']) && !empty($_GET['ip']))
    {//Warning !
     //The is only for testing, to simulate different ip
     //It will not throttle real ip addresses, can be bypassed
     //and lead to vulnerabilities with the throttling script
     //There are also no checks for malicious input
     //In Production to throttle real ip addresses, 
     //remove this and use $_SERVER['REMOTE_ADDR'] 
     
        $ip= $_GET['ip'];
    }
    
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-control: no-store');
    
    //Starts the finite state throttling
    startThrottleStateMachine($ip); 
     
}


?>
