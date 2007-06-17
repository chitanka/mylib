<?php
#  browser utils
#
#  Copyright (C) 2004 Borislav Manolov
#
#  This program is free software; you can redistribute it and/or
#  modify it under the terms of the GNU General Public License
#  as published by the Free Software Foundation; either version 2
#  of the License, or (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License along
#  with this program; if not, write to the Free Software Foundation, Inc.,
#  59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
#  http://www.gnu.org/copyleft/gpl.html
#
#  Author: Borislav Manolov <b.manolov A.T gmail D.O.T com>
#          http://purl.org/NET/borislav/
#
#  This program uses portions of
#    Snoopy - the PHP net client
#    Author: Monte Ohrt <monte@ispi.net>
#    Copyright (c): 1999-2000 ispi, all rights reserved
#    Version: 1.01
#    http://snoopy.sourceforge.net/
#############################################################################

class Browser {

	var $host    = '';        // host for connection
	var $agent   = 'Mozilla/5.0 (PHPBrowser)'; // user agent
	var $cookies = array();   // cookies
	var $print_cookies = false; // whether to print cookies
	var $cookies_file = 'cookies.txt';   // cookies

	# data for basic HTTP Authentication
	var $user = '';
	var $pass = '';

	var $content = '';        // content returned from server
	var $headers = array();   // headers returned from server

	var $error        = '';   // error messages
	var $conn_timeout = 120;   // timeout for socket connection
	var $is_redirect = false; // true if the fetched page is a redirect

	var $fetch_method  = 'GET';     // fetch method
	var $submit_method = 'POST';    // submit method
	var $http_version  = 'HTTP/1.1';// http version
	var $content_type  = array(     // content types
		'text' => 'application/x-www-form-urlencoded',
		'binary' => 'multipart/form-data'
	);
	var $mime_boundary = ''; // MIME boundary for binary submission


  # constructor
  # $params - assoc array (name => value)
  # return nothing
  function Browser($params = array()) {
    settype($params, 'array');
    foreach ( $params as $field => $value ) {
      if ( isset($this->$field) ) {
        $this->$field = $value;
      }
    }
		$this->read_cookies();
    $this->mime_boundary = 'PHPBrowser' . md5( uniqid( microtime() ) );
  }


  # fetch a page
  # $uri - location of the page
	# $do_auth:boolean - add an authentication header
  # return true by success
  function fetch($uri, $do_auth = false) {
    return $this->make_request($uri, $this->fetch_method, '', '', $do_auth);
  }


  # submit an http form
  # $uri  - the location of the page to submit
  # $vars - assoc array with form fields and their values
  # $file - assoc array (field name => file name)
  #         set only by upload
	# $do_auth:boolean - add an authentication header
  # return true by success
  function submit( $uri, $vars, $file = array(), $do_auth = false ) {
    $postdata = '';
    if ( empty($file) ) {
      foreach ( $vars as $key => $val ) {
        $postdata .= urlencode($key) .'='. urlencode($val) .'&';
      }
    } else {
      foreach ( $vars as $key => $val ) {
        $postdata .= '--'. $this->mime_boundary ."\r\n";
        $postdata .= 'Content-Disposition: form-data; name="'. $key ."\"\r\n\r\n";
        $postdata .= $val . "\r\n";
      }

      list($field_name, $file_name) = each($file);
      if ( !is_readable($file_name) ) {
        $this->error = 'File "' . $file_name . '" is not readable.';
        return false;
      }

      $fp = fopen($file_name, 'r');
      $file_content = fread( $fp, filesize($file_name) );
      fclose($fp);
      $base_name = basename($file_name);

      $postdata .= '--'. $this->mime_boundary ."\r\n";
      $postdata .= 'Content-Disposition: form-data; name="'. $field_name .
				'"; filename="' . $base_name . "\"\r\n\r\n";
      $postdata .= $file_content . "\r\n";
      $postdata .= '--'. $this->mime_boundary ."--\r\n";
    }

    $content_type = empty($file)
                  ? $this->content_type['text']
                  : $this->content_type['binary'] ;

    return $this->make_request($uri, $this->submit_method,
                               $content_type, $postdata, $do_auth);
  }


  # get data from server
  # $uri - the location the page
  # $request_method - GET / POST
  # $content_type - content type (for POST submission)
  # $postdata - data (for POST submission)
	# $do_auth:boolean - add an authentication header based on $this->user and $this->pass
  # return true if the request succeeded, false otherwise
  function make_request($uri, $request_method, $content_type = '', $postdata = '', $do_auth = false) {
    $uri_parts = parse_url($uri);
    if ( $uri_parts['scheme'] != 'http') { // not a valid protocol
      $this->error = "Invalid protocol: $uri_parts[scheme]";
      return false;
    }

    $this->host = $uri_parts['host'];
    $fp = @fsockopen($this->host, 80, $errno, $errstr, $this->conn_timeout);
    if ( !$fp ) {
      $this->error = $errno .' / Reason: '. $errstr;
      return false;
    }

    $path = $uri_parts['path'] .
           ($uri_parts['query'] ? '?'. $uri_parts['query'] : '');

    $cookie_headers = '';
    if ($this->is_redirect) {
      $this->set_cookies();
    }

    if ( empty($path) ) { $path = '/'; }
		$headers = "$request_method $path $this->http_version\r\n" .
			"User-Agent: $this->agent\r\nHost: $this->host\r\nAccept: */*\r\n";

	if ($do_auth) {
		$headers .= 'Authorization: Basic '.
			base64_encode($this->user.':'.$this->pass) . "\r\n";
	}

    if ( isset($this->cookies[$this->host]) ) {
			$cookie_headers .= 'Cookie: ';
      foreach ($this->cookies[$this->host] as $cookie_name => $cookie_data) {
        $cookie_headers .= $cookie_name .'='. urlencode($cookie_data[0]) .'; ';
      }
			# add $cookie_headers w/o last 2 chars
			$headers .= substr($cookie_headers, 0, -2) . "\r\n";
    }

    if ( !empty($content_type) ) {
      $headers .= "Content-type: $content_type";
      if ($content_type == $this->content_type['binary'])
        $headers .= '; boundary=' . $this->mime_boundary;
      $headers .= "\r\n";
    }
    if ( !empty($postdata) ) {
      $headers .= "Content-length: ". strlen($postdata) ."\r\n";
    }
    $headers .= "\r\n";
    fwrite( $fp, $headers . $postdata, strlen($headers . $postdata) );

    $this->is_redirect = false;
    unset($this->headers);

    while ( $curr_header = fgets($fp, 4096) )  {
      if ($curr_header == "\r\n")
        break;

      # if a header begins with Location: or URI:, set the redirect
      if ( preg_match('/^(Location:|URI:)[ ]+(.*)/', $curr_header, $matches) ) {
        $this->is_redirect = rtrim($matches[2]);
      }

      $this->headers[] = $curr_header;
    }

    $content = '';
    while ( $data = fread($fp, 500000) ) {
      $content .= $data;
    }

    $this->content = $content;
    fclose($fp);

		if ($this->is_redirect) {
			$this->make_request($this->is_redirect, $request_method,
				$content_type, $postdata);
		}
		return true;
	}

	# read cookies from file
	function read_cookies() {
		$cookies_str = '';
		if (file_exists($this->cookies_file)) {
			$curr_time = time();
			$lines = file($this->cookies_file);
			foreach ($lines as $line) {
				$line = trim($line);
				if ( empty($line) ) { continue; }
				list($host, $cookie_expire, $cookie_name, $cookie_val) = explode("\t", $line);
				# add cookie if not expired
				if ($curr_time < $cookie_expire) {
					$this->cookies[$host][$cookie_name] = array($cookie_val, $cookie_expire);
				}
			}
			# write not expired cookies back to file
			$cookies_str = '';
			foreach ($this->cookies as $host => $cookie_data) {
				foreach ($cookie_data as $cookie_name => $cookie_subdata) {
					$cookies_str .= "$host\t$cookie_subdata[1]\t$cookie_name\t$cookie_subdata[0]\n";
				}
			}
		}
		my_fwrite($this->cookies_file, $cookies_str, 'w');
	}

	# set cookies
	function set_cookies() {
		$len = count($this->headers);
		$cookies_str = '';
		for ($i = 0; $i < $len; $i++) {
			if (preg_match('/^Set-Cookie:\s+([^=]+)=([^;]+);\s+(expires=([^;]+))?/i',
					$this->headers[$i], $matches)) {
				$exp_time = isset($matches[4]) ? strtotime($matches[4]) : time() + 60*60*24*30;
				$cookies_str .= "$this->host\t$exp_time\t$matches[1]\t$matches[2]\n";
				$this->cookies[$this->host][$matches[1]] = array($matches[2], $matches[4]);
				if ( $this->print_cookies ) {
					echo "$matches[1] = $matches[2]; expires at $matches[4]\n";
				}
			}
		}
		my_fwrite($this->cookies_file, $cookies_str);
	}

} // end of class Browser


function my_fwrite($file, $text, $mode='a+') {
	$myFile = @fopen($file, $mode);
	if (! $myFile) return false;
	flock($myFile, LOCK_EX);
	if (! @fputs($myFile, $text)) return false;
	flock($myFile, LOCK_UN);
	if (! @fclose($myFile)) return false;
	return true;
}

?>