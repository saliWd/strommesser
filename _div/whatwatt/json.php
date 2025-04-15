<?php
$data = [ 'id' => 4524, 'interval' => 5.238, 'date_time' => "2025-04-14T21:53:10Z" ];
// should get me something like: "id":4524,"interval":5.238,"date_time":"2025-04-14T21:53:10Z"
header(header: 'Content-type: application/json; charset=utf-8');
echo json_encode(value: $data);