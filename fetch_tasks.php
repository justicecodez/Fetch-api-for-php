<?php
require_once 'api.php';
$data=fetchTasksFromRemote();
$response_data = [
    'status' => 'success',
    'message' => 'Data retrieved successfully',
    'data' => $data,
];

$json_response = json_encode($response_data);
echo $json_response;