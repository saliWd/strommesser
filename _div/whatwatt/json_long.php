<?php
ini_set( option: 'serialize_precision', value: -1 ); // to prevent numbers like 5.238000000000124
 $data = [
   "report" => [
         "id" => 4524, 
         "interval" => 5.238, 
         "date_time" => "2025-04-14T21:53:10Z", 
         "instantaneous_power" => [
            "active" => [
               "positive" => [
                  "total" => 0.001 
               ], 
               "negative" => [
                     "total" => 0 
                  ] 
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
                                       "inductive" => [
                                          "total" => 147.264 
                                       ], 
                                       "capacitive" => [
                                             "total" => 10.076 
                                          ] 
                                    ], 
                                    "exported" => [
                                                "inductive" => [
                                                   "total" => 65.067 
                                                ], 
                                                "capacitive" => [
                                                      "total" => 99.656 
                                                   ] 
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
 
 
// should get me something like: "id":4524,"interval":5.238,"date_time":"2025-04-14T21:53:10Z"
header(header: 'Content-type: application/json; charset=utf-8');
echo json_encode(value: $data);