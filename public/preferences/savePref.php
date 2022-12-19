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
echo 'new connection created';
function destroy($mysqli){
	$thread_id = $mysqli->thread_id;
	$mysqli->kill($thread_id);
	$mysqli->close();
}

function safePOST($post, $conn){
	foreach ($post as $key => $value) {
		if(is_array($post[$key])) $post[$key] = safePOST($post[$key], $conn);
		else $post[$key] = $conn->real_escape_string($value);
	}
	return $post;
}

$_POST['value'] = json_encode($_POST['value']);

$_POST = safePOST($_POST, $conn);

$user = $_POST['user_id'];
$preference = $_POST['preference_id'];
$value = $_POST['value'];

// check if this preference already exists

$query = $conn->prepare("SELECT value FROM general_preferences WHERE user_id=? AND preference_id=?");
$query->bind_param("ss",$user,$preference);
$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0) { // if it does, update it
    $row = $result->fetch_assoc();
    $stmt = $conn->prepare("UPDATE general_preferences SET value=? WHERE user_id=? AND preference_id=?");
    $stmt->bind_param("sss",$value,$user,$preference);
    $stmt->execute();
    echo "Record updated successfully";
} else {
    // if it doesn't, insert it

    $stmt = $conn->prepare("INSERT INTO general_preferences (user_id, preference_id, value, last_modified) VALUES(?,?,?,now())");
    if( false === $stmt)  die('insert failed: ' . htmlspecialchars($stmt->error));
    
    $rc = $stmt->bind_param("sss",$user,$preference,$value);
    if( false === $rc)  die('bind param failed: ' . htmlspecialchars($stmt->error));

    $rc = $stmt->execute();
    if( false === $rc)  die('execute failed: ' . htmlspecialchars($stmt->error));

    echo "Record inserted successfully";
}

destroy($conn);
