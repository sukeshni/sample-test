<?php
header('Content-Type: application/json');
require '../vendor/autoload.php';
$app = new \Slim\Slim();

$app->post('/auth/login',function() {echo validate_login();});
$app->post('/companies/events',function() {echo get_company_events();});
$app->get('/users/events', function() {echo get_user_events();});
$app->post('/users/reserve',function() {echo reserve_my_event();});

$app->run();

function validate_login() {
  $conn = getDBConnection();
  $email = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : null;
  $password = !empty($_POST['password']) ? sha1(mysqli_real_escape_string($conn, $_POST['password'])) : null;

  $sql = "SELECT * FROM users WHERE email='$email' and password='$password'";
  $result = mysqli_query($conn, $sql);
  $rows = mysqli_num_rows($result);
  $return_data = array();

  if ($rows == 1) {
    $row = mysqli_fetch_assoc($result);
    
    $return_data['code'] = 200;
    $return_data['token'] = $row['id']."_".$row['name']."_".$row['group_id']; //md5(uniqid(mt_rand(), true));
    $return_data['user'] = array('id' => (Int) $row['id'],'name' => $row['name'], 'group_id' => (Int) $row['group_id']);
    }
  else {
    $return_data['code'] = 500;
  }
  return json_encode($return_data);
}
function get_company_events() {
  $token = !empty($_POST['token']) ? $_POST['token'] : null;
  $from = !empty($_POST['from']) ? urldecode($_POST['from']) : null;
  if ($from !== null) {$from = format_date($from);}
  $offset = isset($_POST['offset']) ? (Int) $_POST['offset'] : 0;
  $limit = isset($_POST['limit']) ? (Int) $_POST['limit'] : 100;


  $return_data = array();

  if ($token !== null && $token !== 'null') {

    $usr_arr = explode("_", $token);
    $user_id = (Int) $usr_arr[0];
    $group_id = (Int) $usr_arr[2];

    if (isset($group_id) && $group_id === 1) {
      $return_data['code'] = 401;
      return json_encode($return_data);
    }

    if ($from !== null && $limit !==null && $limit !== 0) {
      $conn = getDBConnection();
      $sql = "SELECT e.id, e.user_id, e.name, e.start_date, COALESCE(a.count1,0) AS attendees FROM events AS e LEFT JOIN (SELECT event_id, COUNT(*) AS count1 FROM attends GROUP BY event_id) AS a ON e.id = a.event_id WHERE e.start_date >= '$from' AND e.user_id = $user_id ORDER BY e.start_date ASC LIMIT $limit OFFSET $offset";
      $result = mysqli_query($conn, $sql);
      $rows = mysqli_num_rows($result);
      $return_data['code'] = 200;
      $return_data['events'] = array();
      if($rows > 0) {
        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
          $each_event = array('id' => (Int) $row['id'],'name' => $row['name'], 'start_date' => $row['start_date'], 'number_of_attendees' => (Int) $row['attendees']);
          array_push($return_data['events'], $each_event);
        }
      }
    }
    else {
      //without from parameter; wrong limit: BAD REQUEST
      header( "HTTP/1.1 400 Bad Request" );
      echo http_response_code(400);
      exit();
    }
  }
  else {
    //can not call with out company login
    $return_data['code'] = 401;
  }
  return json_encode($return_data);
}

function get_user_events() {
  $from = !empty($_GET['from']) ? urldecode($_GET['from']) : null;
  if ($from !== null) {$from = format_date($from);}
  $offset = isset($_GET['offset']) ? (Int) $_GET['offset'] : 0;
  $limit = isset($_GET['limit']) ? (Int) $_GET['limit'] : 100;
  $return_data = array();

  if ($from !== null && $limit !== 0) {
    $conn = getDBConnection();
    $sql = "SELECT e.id, e.name, e.start_date,c.id AS c_id,c.name AS c_name FROM events AS e JOIN users c ON c.id = e.user_id AND e.start_date >= '$from' ORDER BY e.start_date ASC LIMIT $limit OFFSET $offset";
    $result = mysqli_query($conn, $sql);
    $rows = mysqli_num_rows($result);
    $return_data['code'] = 200;
    $return_data['events'] = array();
    if($rows > 0){
      while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $company['id'] = (Int) $row['c_id'];
        $company['name'] = $row['c_name'];
        $each_event = array('id' => (Int) $row['id'],'name' => $row['name'], 'start_date' => $row['start_date'], $company);
        array_push($return_data['events'], $each_event);
      }
    }
  }
  else {
      //without from parameter; wrong limit: BAD REQUEST
      header( "HTTP/1.1 400 Bad Request" );
      echo http_response_code(400);
      exit();
  }
  return json_encode($return_data);
}

function reserve_my_event() {
  $token = !empty($_POST['token']) ? $_POST['token'] : null;
  $event_id = !empty($_POST['event_id']) ? $_POST['event_id'] : null;
  $reserve = !empty($_POST['reserve']) ? $_POST['reserve'] : null;

  $return_data = array();

  if ($token !== null && $token !== 'null' && $event_id !== null && $event_id !== 'null' && $reserve !== null && $reserve !== 'null') {

    $usr_arr = explode("_", $token);
    $user_id = $usr_arr[0];
    $group_id = $usr_arr[2];

    if (isset($group_id) && $group_id == 2) {
      $return_data['code'] = 401;
      $return_data['message'] = 'Cannot Reserve';
      return json_encode($return_data);
    }

    $reserve = $reserve === 'true'? true: false;
    $conn = getDBConnection();

    if ($reserve == true) {
      $sql = "INSERT INTO attends VALUES ($user_id,$event_id,NOW())";
      $result = mysqli_query($conn, $sql);

      if($result == FALSE){
        $return_data['code'] = 501;
        $return_data['message'] = 'Already Reserved';
      }
      else{
        $return_data['code'] = 200;
        $return_data['message'] = 'RESERVED';
      }
    }
    if($reserve == false){
      $sql = "DELETE FROM attends WHERE user_id = $user_id AND event_id = $event_id"; 
      $result = mysqli_query($conn, $sql);
      
      if(mysqli_affected_rows($conn) == 0){
        $return_data['code'] = 502;
        $return_data['message'] = 'Already Unreserved';
      }
      else{
        $return_data['code'] = 200;
        $return_data['message'] = 'UNRESERVED';
      }
      return json_encode($return_data);
    }
  }
  else {
  //without token
    $return_data['code'] = 401;
    $return_data['message'] = 'Cannot Reserve';
  }
  return json_encode($return_data);
}

function getDBConnection() {
  $servername = "localhost";
  $username = "root";
  $password = "root";
  $dbname = "eventlist";
  $conn = mysqli_connect($servername, $username, $password, $dbname);
  if (mysqli_connect_errno()) {
    die("Connection failed: " . mysqli_connect_error());
  }
  return $conn;
}

function format_date($d) {
    date_default_timezone_set('UTC');
    return date("Y-m-d", strtotime($d));
}
?>