<?php

function new_mysql_connect()
{
	$database = 'preferences';
	$host = 	'localhost';
	$username = 'preferences';
	$password = '2yog1qa';

	$result = new mysqli($host, $username, $password, $database);
	$errno = $result->connect_errno;
	if($errno){
		throw new Exception('Could not connect to database server');
	} 
	else{
		return $result;
	}
}

$conn = new_mysql_connect();

function destroy($mysqli){
	$thread_id = $mysqli->thread_id;
	$mysqli->kill($thread_id);
	$mysqli->close();
}

function safePOST($post, $conn){
	foreach ($post as $key => $value) {
		if(is_array($post[$key])) $post[$key] = safePOST($post[$key]);
		else $post[$key] = $conn->real_escape_string($value);
	}
	return $post;
}


$_POST = safePOST($_POST, $conn);

$user = $_POST['user_id'];
$preference = $_POST['preference_id'];

// check if this preference already exists



$query = $conn->prepare("SELECT value FROM general_preferences WHERE user_id=? AND preference_id=?");
$query->bind_param("ss",$user,$preference);
$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0) { // if it does, update it
    $row = $result->fetch_assoc();
   
    echo stripcslashes($row['value']);

} else {
    echo "null";
}

destroy($conn);

die();
