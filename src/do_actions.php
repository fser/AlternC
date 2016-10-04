#!/usr/bin/php -q
<?php
/*
   $Id: do_actions.php,v 1.0 2013/04/19 13:40:32 axel Exp $
   ----------------------------------------------------------------------
   AlternC - Web Hosting System
   Copyright (C) 2002 by the AlternC Development Team.
   http://alternc.org/
   ----------------------------------------------------------------------
   Based on:
   Valentin Lacambre's web hosting softwares: http://altern.org/
   ----------------------------------------------------------------------
   LICENSE

   This program is free software; you can redistribute it and/or
   modify it under the terms of the GNU General Public License (GPL)
   as published by the Free Software Foundation; either version 2
   of the License, or (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   To read the license please visit http://www.gnu.org/copyleft/gpl.html
   ----------------------------------------------------------------------
   Original Author of file: Axel Roger
   Purpose of file: Do planed actions on files/directories.
   ----------------------------------------------------------------------
 */
/**
 * This script check the MySQL DB for actions to do, and do them one by one.
 *
 * @copyright AlternC-Team 2002-2013 http://alternc.org/
 */


////////////////////////////////// 
/*
Fixme

 - check all those cases

*/
///////////////////////////////////

// Put this var to 1 if you want to enable debug prints
$debug=0;

// Collects errors along execution. If length > 1, an email is sent.
$errorsList=array();

// Bootstraps 
require_once("/usr/share/alternc/panel/class/config_nochk.php");

// Script lock through filesystem
$admin->stop_if_jobs_locked();

if( !defined("ALTERNC_DO_ACTION_LOCK")){
    define("ALTERNC_DO_ACTION_LOCK",'/var/run/alternc/do_actions_cron.lock');
}

$SCRIPT='/usr/bin/php do_actions.php';
$MY_PID=getmypid();
$FIXPERM='/usr/lib/alternc/fixperms.sh';


/**
 * 
 * Debug function that print infos
 * 
 * @global int $debug
 * @param type $mess
 */
function d($mess){
  global $debug;
  if ($debug == 1)
    echo "$mess\n";
}

/**
 * Function to mail the panel's administrator if something failed
 * @global array $errorsList
 * @global type $L_FQDN
 */
function mail_it(){
  global $errorsList,$L_FQDN;
  // Forces array
  if( !is_array($errorsList)){
      $errorsList = array($errorsList);
  }
  // Builds message from array
  $msg = implode("\n", $errorsList);
  // Attempts to send email
  // @todo log if fails 
  mail("alterncpanel@$L_FQDN",'Script do_actions.php issues',"\n Errors reporting mail:\n\n$msg");
}

/**
 * Common routine for system calls
 * 
 * @param type $command the command
 * @param type $parameters of the command (they are going to be protected)
 * @return array('output'=>'output of exec', 'return_val'=>'returned integer of exec') 
 */
function execute_cmd($command, $parameters=array()) {
  $cmd_line = "$command ";
  if (!empty($parameters)) {
    if (is_array($parameters)) {
      foreach($parameters as $pp) {
        $cmd_line.= " ".escapeshellarg($pp)." ";
      }
    } else {
      $cmd_line.= " ".escapeshellarg($parameters)." " ;
    }
  }
  $cmd_line.= " 2>&1";
  exec($cmd_line, $output, $code);
  return array('executed' => $cmd_line, 'output'=>$output, 'return_val'=>$code);
}

/** Check if a file or folder is in the list of allowed 
 *  path (after dereferencing all ../ and symlinks
 * @param $path string the path to check against 
 * @return string the dereferenced path, or FALSE if the path is NOT allowed (/var/www/alternc /var/mail/alternc) 
 */
function my_realpath($path) {
    global $L_ALTERNC_HTML, $L_ALTERNC_MAIL;
    // add here any allowed path: 
    $allowed=array(realpath($L_ALTERNC_HTML)."/", realpath($L_ALTERNC_MAIL)."/");
    $path=realpath($path);
    foreach($allowed as $one) {
        // the path must be BELOW each allowed folder. forbid anything 
        if (strlen($path)>strlen($one) && substr($path,0,strlen($one))==$one) {
            return $path;
        }
    }
    return false;
}


function add_error($error)
{
  global $errorsList;
  array_push($errorsList, $error);
}

/**
 * This functions tries to take the lock.
 * If the lock is already held, and the process is dead,
 * it tries to take-over.
 *
 * If fatal errors are encountered, Exceptions are thrown
 * 
 * @return True if the lock was successfully held
 * @return False otherwize
 */
function try_lock()
{
  // Check if script isn't already running
  if (file_exists(ALTERNC_DO_ACTION_LOCK) !== false){
    d("Lock file already exists. ");
    // Check if file is in process list
    $PID=file_get_contents(ALTERNC_DO_ACTION_LOCK);
    d("My PID is $MY_PID, PID in the lock file is $PID");
    if ($PID == exec("pidof $SCRIPT | tr ' ' '\n' | grep -v $MY_PID")){
      // Previous cron is not finished yet, just exit
      d("Previous cron is already running, we just exit and let it finish :-)");
      return FALSE;
    }else{
      // Previous cron failed!
      add_error("Lock file already exists. No process with PID $PID found! Previous cron failed...");
    }
    // Lock with the current script's PID
    if (file_put_contents(ALTERNC_DO_ACTION_LOCK,$MY_PID) === false)
      throw new Exception('Cannot open/write ALTERNC_DO_ACTION_LOCK');
    
    return TRUE;
  }
  else
  {
    // Lock with the current script's PID
    if (file_put_contents(ALTERNC_DO_ACTION_LOCK,$MY_PID) === false)
      throw new Exception("Cannot open/write ALTERNC_DO_ACTION_LOCK");

    return TRUE;
  }
}

function release_lock()
{
  // Unlock the script
  // @todo This could be handled by m_admin
  unlink(ALTERNC_DO_ACTION_LOCK);
}


function recover_actions()
{
  // Get the action(s) that was processing when previous script failed
  // (Normally, there will be at most 1 job pending... but who know?)
  while($cc=$action->get_job())
  {
    
    $c=$cc[0];
    $params=unserialize($c["parameters"]);
    
    // We can resume these types of action, so we reset the job to process it later
    d("Previous job was the n°".$c["id"]." : '".$c["type"]."'");

    if($c["type"] == "CREATE_FILE" && is_dir(dirname($params["file"])) || $c["type"] == "CREATE_DIR" || $c["type"] == "DELETE" || $c["type"] == "FIX_DIR" || $c["type"] == "FIX_FILE"){
      d("Reset of the job! So it will be resumed...");
      $action->reset_job($c["id"]);
    }else{
      // We can't resume the others types, notify the fail and finish this action
      add_error("Can't resume the job n°".$c["id"]." action '".$c["type"]."', finishing it with a fail status.");
      if(!$action->finish($c["id"],"Fail: Previous script crashed while processing this action, cannot resume it.")){
	throw new Exception("Cannot finish the action! Error while inserting the error value in the DB for action n°".$c["id"]." : action '".$c["type"]);
      }
    }
    
  }
  
}

function do_actions()
{

  $actions = array('FIX_USER' => function () { /*  Create the directory and make parent directories as needed */ return execute_cmd("$FIXPERM -u", $params["uid"]); },
		   'CHMOD'    => function () {
		     $filename = my_realpath($params["filename"]);
		     if ($filename===false)
		       throw new Exception("Fail: path not allowed ($filename)");

		     $perms=$params["perms"];

		     // Checks the file or directory exists
		     if( !is_dir($filename) && ! is_file($filename))
		       throw new Exception("Fail: cannot retrieve CHMOD filename");

		     // Checks the perms are correct
		     if ( !is_int( $perms))
		       throw new Exception("Fail: Incorrect perms : $perms");

		     // Attempts to change the rights on the file or directory
		     if( !chmod($filename, $perms))
		       throw new Exception("Fail: cannot change perms ($perms) on filename ($filename)");

		     return True;
		   },
		   'CREATE_FILE' => function () {
		     $dirname=my_realpath(dirname($params["filename"]));
		     $filename=basename($params["filename"]);
		     if ($dirname===false)
		       throw new Exception("Fail: path not allowed");

		     $params["file"]=$dirname.DIRECTORY_SEPARATOR.$filename;
		     if(!file_exists($params["file"])) {
		       if ( file_put_contents($params["file"], $params["content"]) === false )
			 throw new Exception("Fail: can't write into file ".$params["file"]);

		       if (!chown($params["file"], $r["user"]))
			 throw new Exception("Fail: cannot chown ".$params["file"]);
		     } else {
		       // should be recoverable
		       add_error("Fail: file already exists ".$params["file"]);
		       return False;
		     }
		     return True;
		   },
		   'CREATE_DIR' => function () {
		     $dirname=my_realpath(dirname($params["dir"]));
		     $filename=basename($params["dir"]);
		     if ($dirname===false)
		       throw new Exception("Fail: path not allowed");

		     $params["dir"]=$dirname.DIRECTORY_SEPARATOR.$filename;
		     // Create the directory and make parent directories as needed
		     return execute_cmd("$SU mkdir", array('-p', $params["dir"]));
		   },
		   'DELETE' => function () {
		     $dirname=my_realpath($params["dir"]);
		     if ($dirname===false)
		       throw new Exception ("Fail: path not allowed");

		     // Delete file/directory and its contents recursively
		     $returned = execute_cmd("$SU rm", array('-rf', $dirname));
		     return True;
		   },
		   'MOVE' => function () {
		     // If destination dir does not exists, create it
		     $dirname=my_realpath(dirname($params["dst"]));
		     $filename=basename($params["dst"]);
		     
		     if ($dirname===false)
		       throw new Exception("Fail: path not allowed");

		     $params["dst"]=$dirname.DIRECTORY_SEPARATOR.$filename;
		     $params["src"]=my_realpath($params["src"]);
		     if ($params["src"]===false)
		       throw new Exception("Fail: path not allowed");

		     if (!is_dir($params["dst"])) {
		       if ( @mkdir($params["dst"], 0777, true)) {
			 if ( @chown($params["dst"], $r["user"]) ) {
			   return execute_cmd("$SU mv -f", array($params["src"], $params["dst"])); 
			 }
		       }
		     }
		     throw new Exception("Fail: cannot create ".$params["dst"]);
		   },
		   'FIX_DIR' => function () {
		     $params["dir"]=my_realpath($params["dir"]);
		     if ($params["dir"]===false)
		       throw new Exception("Fail: path not allowed");
		   
		     $returned = execute_cmd($FIXPERM, array('-d', $params["dir"]));
		     if($returned['return_val'] != 0)
		       throw new Exception("Fixperms.sh failed, returned error code : ".$returned['return_val']);

		     return True;
		   },
		   'FIX_FILE' => function () {
		     $params["file"]=my_realpath($params["file"]);
		     if ($params["file"]===false)
		       throw new Exception("Fail: path not allowed");

		     $returned = execute_cmd($FIXPERM, array('-f', $params["file"]));
		     if($returned['return_val'] != 0)
		       throw new Exception("Fixperms.sh failed, returned error code : ".$returned['return_val']);
		   });

  $output = array();
		   
  //We get the next action to do
  while ($rr=$action->get_action())
  {
    $r=$rr[0];
    $return="OK";

    // Do we have to do this action with a specific user?
    if($r["user"] != "root")
      $SU="su ".$r["user"]." 2>&1 ;";
    else
      $SU="";
    
    // We lock the action
    d("-----------\nBeginning action n°".$r["id"]);
    $action->begin($r["id"]);
    // We process it
    $params=@unserialize($r["parameters"]);
    // We exec with the specified user
    d("Executing action '".$r["type"]."' with user '".$r["user"]."'");

    try {
      if (in_array($r["type"], $actions))
      {
	$outcome = $actions[ $r["type"] ]();
	if ($outcome !== TRUE)
	  array_push($output, $outcome);
      }
      else
	$output=array("Fail: Sorry dude, i do not know this type of action");
      break;
    }
    catch (Exception $e)
    {
      echo "Error handling action n°".$r["id"]." '".$r["type"]."' failed! With user: ".$r["user"]."\nHere is the complete output:" . $e.getMessage();
    }
  }
  // Get the error (if exists).
  if(isset($output[0])){
    $return=$output[0];
    $errorsList[]="\nAction n°".$r["id"]." '".$r["type"]."' failed! With user: ".$r["user"]."\nHere is the complete output:\n".print_r($output);
  }
  // We finished the action, notify the DB.
  d("Finishing... return value is : $return\n");
  if(!$action->finish($r["id"],addslashes($return))){
    $errorsList[]="Cannot finish the action! Error while inserting the error value in the DB for action n°".$r["id"]." : action '".$r["type"]."'\nReturn value: ".addslashes($return)."\n";
    break; // Else we go into an infinite loop... AAAAHHHHHH
  }
}



try {
  
  if (!try_lock()) {
    echo "Could not take lock:";
    echo implode("\n", $errorsList);
    exit (0);
  }

  recover_actions();
  do_actions();

  release_lock();

  // If an error occured, notify it to the admin
  if(count($errorsList)) {
    mail_it();
    if( (php_sapi_name() === 'cli') ){
      echo _("errors were met");
      var_dump($errorsList);

    } 
  }
  
  // Exit this script
  exit(0);

}
catch (Exception $e) {
  echo "Error acquiring lock: $e->getMessage()\n";
  exit (1);
}


