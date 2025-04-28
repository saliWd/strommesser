<?php
ini_set( option: 'serialize_precision', value: -1 );
header(header: 'Content-type: application/json; charset=utf-8');

$data_whatwatt = [
   "report" => [
      "id" => 4524, 
      "interval" => 5.238, 
      "date_time" => "2025-04-14T21:53:10Z", 
      "instantaneous_power" => [
         "active" => [
            "positive" => ["total" => 0.001], 
            "negative" => ["total" => 0] 
         ] 
      ], 
      "energy" => [
                     "active" => [
                        "positive" => [
                           "total" => 163.26, 
                           "t1" => 127.896, 
                           "t2" => 35.355 
                        ], 
                        "negative" => [
                              "total" => 506.341, 
                              "t1" => 92.135, 
                              "t2" => 414.198 
                           ] 
                     ], 
                     "reactive" => [
                                 "imported" => [
                                    "inductive" => ["total" => 147.264], 
                                    "capacitive" => ["total" => 10.076] 
                                 ], 
                                 "exported" => [
                                             "inductive" => ["total" => 65.067], 
                                             "capacitive" => ["total" => 99.656] 
                                          ] 
                              ] 
                  ], 
      "conv_factor" => 1 
   ], 
   "meter" => [
      "status" => "OK", 
      "interface" => "MBUS", 
      "protocol" => "DLMS", 
      "id" => "72913313", 
      "vendor" => "Landis+Gyr", 
      "prefix" => "LGZ" 
   ], 
   "system" => [
      "id" => "ECC9FF5C80B0", 
      "date_time" => "2025-04-14T20:53:12Z", 
      "boot_id" => "3D5B5E1A", 
      "time_since_boot" => 20815 
   ] 
];
$data_gplug = [
   "StatusSNS" => [
         "Time" => "2025-04-28T21:19:10", 
         "z" => [
            "SMid" => "72913313", 
            "Pi" => 0.008, 
            "Po" => 0, 
            "I1" => 0.35, 
            "I2" => 0.51, 
            "I3" => 0.14, 
            "Ei" => 170.317, 
            "Eo" => 643.856, 
            "Ei1" => 132.179, 
            "Ei2" => 38.129, 
            "Eo1" => 132.9, 
            "Eo2" => 510.943, 
            "Q5" => 156.411, 
            "Q6" => 10.597, 
            "Q7" => 88.391, 
            "Q8" => 124.278 
         ] 
      ] 
];
if (strcmp(string1: $_GET['reader'],string2: 'whatwatt') === 0) {
   $data = $data_whatwatt;
} else { // gplug
   $data = $data_gplug;
}
// should get me something like: "id":4524,"interval":5.238,"date_time":"2025-04-14T21:53:10Z"
echo json_encode(value: $data);