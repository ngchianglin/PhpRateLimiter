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

A Simple rate limiting php script 
using a mariadb sql table to track the state
Limit requests based on unique remote ip addresss 
and a counter for each ip.
The script only supports IPv4. 

The maria db table consists of the following

id, INT the primary key (derived from ip address, ip2long)
ip, the character reprsentation of ip address (xxx.xxx.xxx.xxx)
stime, the start time of the throttle interval
count, counter for this interval

Refer to the following link for detailed article on usage
https://www.nighthour.sg/articles/2017/php-rate-limiter-finite-state.html

Ng Chiang Lin
Feb 2017

*/

define("THROTTLE_INTERVAL", 60); //in seconds
define("INTERVAL_RATE", 5); //Number allowed in throttle interval. Eg. 5 emails per 60 seconds

/* 
Connects to the database and return the pdo database object. 
The connection is to a mariadb instance over TLS connection.
A good tutorial on php PDO,((The only proper) PDO tutorial)  
https://phpdelusions.net/pdo

Any exception will be bubbled up to the container. In production, remember to 
disable the display of errors. Errors can be sent to the error log. 
*/

function getPDO()
{
    $pdo=null;
    
    $driver="mysql";
    $host = 'myhostname.localdomain';
    $db   = 'throttledb1';
    $user = 'throttleuser';
    $pass = 'mystrongpassword';
    $charset = 'utf8';
    $dsn = "$driver:host=$host;dbname=$db;charset=$charset";
    $opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_KEY    =>'<location to client key>/client-key.pem',
    PDO::MYSQL_ATTR_SSL_CERT=>'<location to client cert>/client-cert.pem',
    PDO::MYSQL_ATTR_SSL_CA    =>'<location to ca cert>/ca-cert.pem'
    ];

    $pdo = new PDO($dsn, $user, $pass, $opt);
    
    return $pdo;
}

/* 
   Retrieves the current counter for an ip address in the 
   database. 
   
   Takes the pdo database object and a int representing the 
   ip address. The int is obtained by applying ip2long()
   to the ip address. Returns false if ip address counter
   is not present else an associative array of the result is 
   returned. 
*/
function getIPAddressRecord($pdo, $id)
{
    $result = null; 
    $stmt = null;
    try
    {
       $pdo->beginTransaction();
       //Prepared statement to prevent SQL injection
       $stmt = $pdo->prepare('Select id, ip, stime, count from t1 where id = ? '); 
       $stmt->execute([$id]);
       $result = $stmt->fetch();
       $stmt = null;
       $pdo->commit();
       
    }
    catch(PDOException $e)
    {//Nothing to roll back. 
        $stmt=null;
        error_log("getIPAddressRecord transaction error " . $e ." \n", 0);
    }
    return $result;
}

/* 
   Creates a new ip address counter in the database. 
   The primary key is based on the integer value of the ip address by
   applying ip2long() function to the ip address. 
   Trying to add the same entry again(when another process has already added)
   will throw an exception that has to be handled.  
   Takes the pdo database object and ip address as parameters.
   Returns the rows updated, where success will be 1 and failure 0. 
   
*/
function createIPCounter($pdo, $ip )
{
    $updatedrows=0; 
    try
    {   //Newly created row timestamp field will be current time when set to NULL
        $stmt = $pdo->prepare('INSERT into t1 (id,ip,count, stime) values (?, ?, 0, NULL);');
        $ret1 = $stmt->execute([ip2long($ip) , $ip]);
        $updatedrows = $stmt->rowCount();
        $stmt = null;
    }
    catch(PDOException $e)
    { 
       error_log("Add counter exception " . $e . " \n", 0);
    }
    
    return $updatedrows; 
}


/* 
Increments the counter for an ip address 
Takes the pdo database object , the id representation of the 
ip address(ip2long() apply to ip)
Wraps in transaction to prevent concurrency issue
Returns false if the transaction fail, true otherwise.  
*/
function updateCount($pdo, $id)
{
    $stmt = null;
    try
    {
        $pdo->beginTransaction();
        $stmt= $pdo->prepare('Update t1 set count = count + 1 where id = ?');
        $stmt->execute([$id]);
        $pdo->commit();      
    }
    catch(PDOException $e)
    {
         error_log("Update Count transaction error " . $e ." \n", 0);
         $pdo->rollBack();
         $stmt =null; 
         return false;
    }
    
    $stmt = null;
    return true;
}


/* 

Reset to a new throttle Interval 
Wrap in transaction as there are two updates together
Takes a pdo database object and the id representing the ip
address (ip2long() apply to ip address).

Returns true on success.

*/

function resetThrottleInterval($pdo, $id )
{
    $terminate_counter =0;
    $end=false;
    $max_retries = 10; 
    
    while(!$end)
    { //Tries the transaction up to 10 times if it 
      //is initially not successful
        
        try
        {
            $pdo->beginTransaction();
            //set a new throttle start interval
            $stmt1 = $pdo->prepare('Update t1 set stime = CURRENT_TIMESTAMP() where id = ?');
            $stmt1->execute([$id]);
            
            //set the counter to 0
            $stmt2 = $pdo->prepare('Update t1 set count=0 where id = ?');
            $stmt2->execute([$id]);
          
            $pdo->commit();
            //Commited successfully
            $end = true;            
            $stmt1=null;
            $stmt2=null;
            
        }
        catch(PDOException $e)
        {
             error_log("resetThrottleInterval transaction error " . $e ." \n", 0);
             $pdo->rollBack();
             $stmt1=null;
             $stmt2=null;
        }
               
        if(!$end)
        {//Transaction didn't succeed
           if($terminate_counter > $max_retries)
           { //Exceed max retries, can be due to high concurrency terminate process
               error_log("resetThrottleInterval maximum retries, terminating\n", 0);
               exit(1);
           }
          $terminate_counter ++;
          randSleep(); //random sleep to prevent tight loop
        }
        
    }
    
    return true;
}


/* 
 Checks whether the ip counter is still within throttle interval 
 Takes an String representing the start time of the throttle
 interval. The String is converted to unix time using
 strtotime().
 
 Returns true if still within interval, false otherwise

*/
function checkWithinThrottleInterval($stime)
{
    
    $throttle_interval = THROTTLE_INTERVAL; 
    $starttime = strtotime($stime); //Time that the throttling interval start
    $currenttime = time();
    $diff = ($currenttime - $starttime);
    
    if($diff < 0 )
    {//negative time, log an error
     //Attempt to allow service to continue in this case
       error_log("Something horrible has happened, negative time occurs \n", 0);
      //Returning false will allow a new interval to be created hopefully
      //solving the time issue. 
       return false; 
    }
    
    if( $diff > $throttle_interval )
    {//Interval already lapses 
        return false;
    }
    else
    {// Still within interval
        return true; 
    }
    
}


/* Checks if counter value is within the permitted rate set
   for the throttle interval. 
   Takes an int representing the count value of the
   ip counter. 
   Returns true if the count is less than the rate,
   false otherwise. 
   
*/
function checkCountWithinRate($count)
{
    $interval_rate = INTERVAL_RATE ;  
    
    if($count < $interval_rate && $count >= 0)
    { //check for greater than 0 to prevent
      //possible overflow
        return true;
    }
    else
    {
        return false;
    }
}

/* 
A random micro second sleep function 
to prevent tight loop taking up too much CPU time
*/

function randSleep()
{
    //Sleep between 100 to 200 milliseconds
    $milliseconds =  mt_rand(100, 200);
    $microseconds = $milliseconds * 1000;
    usleep($microseconds);
}


/*
Finite state machine to handle the rate limiting conditions and throttling. 
Takes a ip address string as argument. 
If it is within the rate limit, the allow() function will be called 
to do the actual work. 
If rate limit is exceeded, disallow() function is called. 
These 2 functions are defined by the actual scripts that uses
this throttling finite state machine. 

*/
function startThrottleStateMachine($remoteip)
{
    $STATES = [
    'INIT' => 0,
    'IPEXIST' => 1,
    'IPNOTEXIST' => 2,
    'WITHININT' => 3, 
    'EXCEEDINT'=>4,
    'ALLOW' => 5,
    'DISALLOW'=>6
    ];
    
    $ip = $remoteip; 
    $currentstate = $STATES['INIT'];        
    $pdo = getPDO();  //Get the database object
    $end = false; 
    $result = null; 
    $terminate_counter =0;
    $maxcycle=10; 
    
    while(!$end)
    {
        switch ($currentstate)
        {
            case $STATES['INIT']:
                //initial state
                $result = getIPAddressRecord($pdo ,ip2long($ip)); 
                if($result)
                {   
                    $currentstate = $STATES['IPEXIST'];
                }
                else
                {//new ip 
                    $currentstate = $STATES['IPNOTEXIST'];
                }
            
                break;
            case $STATES['IPEXIST']:   
                 //ip counter exists
                 if(!updateCount($pdo, ip2long($ip)))
                 {
                     exit(1);
                 }
                 if(checkWithinThrottleInterval( $result['stime'] ) )
                 { //within throttle interval
                     $currentstate = $STATES['WITHININT'];
                 }
                 else
                 { //exceeds interval
                     $currentstate = $STATES['EXCEEDINT'];
                 }

                break;
            case $STATES['IPNOTEXIST']:   
                 //Ip counter does not exists
                 createIPCounter($pdo, $ip );
                 $result = getIPAddressRecord($pdo ,ip2long($ip)); 
                 if(!$result)
                 {
                     error_log("Unable to create new IP record", 0);
                     exit(1); 
                 }
                 $currentstate = $STATES['IPEXIST'];
                break;

            case $STATES['WITHININT']:   
                 //within the throttle interval
                 if(checkCountWithinRate( (int)$result['count'] ))
                 {
                     $currentstate = $STATES['ALLOW'];
                 }
                 else
                 {
                     $currentstate = $STATES['DISALLOW'];
                     
                 }
                break;

            case $STATES['EXCEEDINT']:   
                  //exceeds the throttle interval, set a new one
                   resetThrottleInterval($pdo, ip2long($ip) );
                   $result = getIPAddressRecord($pdo ,ip2long($ip)); 
                   if(!$result)
                   {
                       error_log("Unable to obtain new ip interval record after resetThrottleInterval!", 0);
                       exit(1); 
                   }
                   $currentstate = $STATES['IPEXIST'];
                break;
            case $STATES['ALLOW']:   
                 //Within the allowed rate limit
                 //The actual work and function that you want to do comes here
                 allow($ip, $result); 
                 $end=true;
                 $pdo=null;
                 break;       
            case $STATES['DISALLOW']:   
                 //Exceeds the rate limit silently ignore, log or send error message
                 disallow($ip, $result); 
                 $end=true;
                 $pdo=null;
                 break;                       
            default:
                //something is horribly wrong, shouldn't come here
                error_log("Unknown state terminating \n", 0);
                exit(1);
                 
            
        }
        
        //Additional safeguard to prevent infinite loop
        if($terminate_counter > $maxcycle)
        {
           error_log("Something horrible has happened, state loop exceeded max cycle, terminating \n",0);
           exit(1);           
        }       
        $terminate_counter++;
    }
    
}



?>

