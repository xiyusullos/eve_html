<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/includes/api_authentication.php
 *
 * Users related functions for REST APIs.
 *
 * @author Andrea Dainese <andrea.dainese@gmail.com>
 * @copyright 2014-2016 Andrea Dainese
 * @license BSD-3-Clause https://github.com/dainok/unetlab/blob/master/LICENSE
 * @link http://www.unetlab.com/
 * @version 20160719
 */

/*
 * Function to login a user.
 *
 * @param	PDO			$db				PDO object for database connection
 * @param	Array		$p				Parameters
 * @param	String		$cookie			Session cookie
 * @return	bool						True if valid
 */
function apiLogin($db, $html5_db, $p, $cookie) {
	if (!isset($p['username'])) {
		$output['code'] = 400;
		$output['status'] = 'fail';
		$output['message'] = $GLOBALS['messages'][90011];
		return $output;
	} else {
		$username = $p['username'];
	}

	if (!isset($p['password'])) {
		$output['code'] = 400;
		$output['status'] = 'fail';
		$output['message'] = $GLOBALS['messages'][90012];
		return $output;
	} else {
		$hash = hash('sha256', $p['password']);
	}
	
	if (!isset($p['html5'])) $p['html5'] = 1;

	$rc = deleteSessions($db, $username);
	if ($rc !== 0) {
		// Cannot delete old sessions
		$output['code'] = 500;
		$output['status'] = 'error';
		$output['message'] = $GLOBALS['messages'][$rc];
		return $output;
	}

	$query = 'SELECT COUNT(*) as urows FROM users WHERE username = :username AND password = :password;';
	$statement = $db -> prepare($query);
	$statement -> bindParam(':username', $username, PDO::PARAM_STR);
	$statement -> bindParam(':password', $hash, PDO::PARAM_STR);
	$statement -> execute();
	$result = $statement -> fetch();

	if ($result['urows'] == 1) {
		// User/Password match
		if (checkUserExpiration($db, $username) === False) {
			$output['code'] = 401;
			$output['status'] = 'unauthorized';
			$output['message'] = $GLOBALS['messages'][90018];
			return $output;
		}

		// UNetLab is running in multi-user mode
		$rc = configureUserPod($db, $username);
		if ($rc !== 0) {
			// Cannot configure a POD
			$output['code'] = 500;
			$output['status'] = 'error';
			$output['message'] = $GLOBALS['messages'][$rc];
			return $output;
		}

		$rc = updateUserCookie($db, $username, $cookie);
		if ($rc !== 0) {
			// Cannot update user cookie
			$output['code'] = 500;
			$output['status'] = 'error';
			$output['message'] = $GLOBALS['messages'][$rc];
			return $output;
		}
		if ( $p['html5'] == 1 ) {
		//enable on database
			$query = "update users set html5 = 1 where username = '".$username."' ;";
			$statement = $db -> prepare($query);
			$statement -> execute();
			// Add token guacamole to user
			// HTML5 mode -> add cokies


			// Old Code
			/*
			$query = "delete from guacamole_user where username = '".$username."';";
			$statement = $html5_db -> prepare($query);
			$statement -> execute();
	
			$query = "select id from pods where username = '".$username."';";
			$statement = $db -> prepare($query);
			$statement -> execute();
			$result = $statement -> fetch();
			$pod = $result["id"];
	
                        $query = "select password from users  where username = '".$username."' ;";
                        $statement = $db -> prepare($query);
                        $statement -> execute();
                        $result = $statement -> fetch();
                        $user_password = $result['password'];

                        $query = "replace into guacamole_user(user_id,username, password_hash, password_date ) values  ( ".($pod+1000)." , '".$username."', UNHEX(SHA2('".$user_password."',256) ), '".date("Y-m-d H:i:s")."');";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();
			
	
			$query="replace into guacamole_user_permission ( user_id , affected_user_id , permission ) values ( '".($pod+1000)."' , '".($pod+1000)."' , 'UPDATE' ) ;";
			$statement = $html5_db -> prepare($query);
			$statement -> execute();
			*/

			// New code
                       $query = "select id from pods where username = '".$username."';";
                        $statement = $db -> prepare($query);
                        $statement -> execute();
                        $result = $statement -> fetch();
                        $pod = $result["id"];

                        $query = "delete from guacamole_user where user_id = '".($pod+1000)."';";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();

                        $query = "delete from guacamole_entity where entity_id = '".($pod+1000)."';";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();

                        $query = "replace into guacamole_entity( entity_id, name, type ) values  ( ".($pod+1000)." , '".$username."', 'USER' );";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();

                        $query = "select password from users  where username = '".$username."' ;";
                        $statement = $db -> prepare($query);
                        $statement -> execute();
                        $result = $statement -> fetch();
                        $user_password = $result['password'];

                        $query = "replace into guacamole_user(user_id, entity_id, password_hash, password_date ) values  ( ".($pod+1000)." , '".($pod+1000)."', UNHEX(SHA2('".$user_password."',256) ), '".date("Y-m-d H:i:s")."');";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();

                        $query="delete from  guacamole_user_permission where  entity_id = '".($pod+1000)."' ;";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();

                        $query="insert into guacamole_user_permission ( entity_id , affected_user_id , permission ) values ( '".($pod+1000)."' , '".($pod+1000)."' , 'UPDATE' ) ;";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();

                        $query="insert into guacamole_user_permission ( entity_id , affected_user_id , permission ) values ( '".($pod+1000)."' , '".($pod+1000)."' , 'READ' ) ;";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();

                        $query="update guacamole_connection_permission set permission = 'UPDATE' where entity_id = ".($pod+1000)." ;";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();
			// End new code 
	
			$rc = updateUserToken($db,$username,$pod);
		} else { 
			$query = "select id from pods where username = '".$username."';";
                        $statement = $db -> prepare($query);
                        $statement -> execute();
                        $result = $statement -> fetch();
			$pod = $result["id"];

			$query = "update users set html5 = 0 where username = '".$username."' ;";
	                $statement = $db -> prepare($query);
	                $statement -> execute();

                        $query = "delete from guacamole_user where user_id = '".($pod+1000)."';";
                        $statement = $html5_db -> prepare($query);
                        $statement -> execute();


		};

		$output['code'] = 200;
		$output['status'] = 'success';
		$output['message'] = $GLOBALS['messages'][90013];
	} else if ($result['rows'] == 0) {
		// User/Password does not match
		$output['code'] = 400;
		$output['status'] = 'fail';
		$output['message'] = $GLOBALS['messages'][90014];
	} else {
		// Invalid result
		$output['code'] = 500;
		$output['status'] = 'error';
		$output['message'] = $GLOBALS['messages'][90015];
	}

	return $output;
}

/*
 * Function to logout a user.
 *
 * @param	PDO			$db				PDO object for database connection
 * @param	String		$cookie			Session cookie
 * @return	bool						True if valid
 */
function apiLogout($db, $cookie) {
	$query = 'UPDATE users SET cookie = NULL, session = NULL WHERE cookie = :cookie;';
	$statement = $db -> prepare($query);
	$statement -> bindParam(':cookie', $cookie, PDO::PARAM_STR);
	$statement -> execute();
	//$result = $statement -> fetch();

	$output['code'] = 200;
	$output['status'] = 'success';
	$output['message'] = $GLOBALS['messages'][90019];
	return $output;
}

/*
 * Function to check authorization
 *
 * @param	PDO			$db				PDO object for database connection
 * @param	String		$cookie			Session cookie
 * @return	Array						Username, role, tenant if logged in; JSend data if not authorized
 */
function apiAuthorization($db, $cookie) {
	$output = Array();
	$user = getUserByCookie($db, $cookie);	// This will check session/web/pod expiration too

	if (empty($user)) {
		// Used not logged in
		$output['code'] = 412;
		$output['status'] = 'unauthorized';
		$output['message'] = $GLOBALS['messages']['90001'];
		return Array(False, False, $output);
	} else {
		// User logged in
		$rc = updateUserCookie($db, $user['username'], $cookie);
		if ($rc !== 0) {
			// Cannot update user cookie
			$output['code'] = 500;
			$output['status'] = 'error';
			$output['message'] = $GLOBALS['messages'][$rc];
			return Array(False, False, $output);
		}
	}

	return Array($user, $user['tenant'], False);
}
?>
