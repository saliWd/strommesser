def get_wlan_config():
    config_wlan = dict([
        ('ssid','strommesser.ch'),
        ('pw','messerPW')
    ])
    return(config_wlan)

def get_device_config():
    config_device = dict([
        ('userid','3'),
        ('post_key','X6O4wqb339L3w9Fmjl9kPVAkcwXkVhTj')
    ])
    return(config_device)   

def get_debug_settings():
    debug_settings = dict([
        ('wlan','simulated'), # can be 'real'|'simulated'. When simulated, json_data must be 'file'
        ('sleep','normal'),    # can be 'normal'|'short'
        ('json_data','file'), # can be 'local_net'|'web'|'file'. Latter two are for debug
        ('tx_to_server',True) # can be True|False. whether data are updated on the server (every 2 mins)
    ])
    return(debug_settings)

def debug_jdata():
    jdata = {"report":{"id":292,"interval":2.737,"date_time":"2025-04-22T18:32:05Z","instantaneous_power":{"active":{"positive":{"total":0},"negative":{"total":1.803}}},"energy":{"active":{"positive":{"total":167.508,"t1":130.297,"t2":37.202},"negative":{"total":575.932,"t1":107.105,"t2":468.817}},"reactive":{"imported":{"inductive":{"total":152.881},"capacitive":{"total":10.117}},"exported":{"inductive":{"total":78.124},"capacitive":{"total":114.091}}}},"conv_factor":1},"meter":{"status":"OK","interface":"MBUS","protocol":"DLMS","id":"72913313","vendor":"Landis+Gyr","prefix":"LGZ"},"system":{"id":"ECC9FF5C80B0","date_time":"2025-04-22T17:32:12Z","boot_id":"E766FCAC","time_since_boot":1345}}
    return jdata

