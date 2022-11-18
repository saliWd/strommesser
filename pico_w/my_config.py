# returns the ssid and the password of the WLAN connection
def get_wlan_config():
    config_wlan = dict([
        ("ssid","strommesser"),
        ("pw","publicPW")
        ])
    return(config_wlan)

# device name must not be more than 8 characters (stored in db)
def get_device_config():
    config_device = dict([
        ("device_name","testdev"),
        ("post_key","X6O4wqb339L3w9Fmjl9kPVAkcwXkVhTj")
        ])
    return(config_device)   

# debug settings
def get_debug_settings():
    debug_settings = dict([
        ("print",True),
        ("ir_sim",True),
        ("wlan_sim",False),
        ("sleep",True),
        ("wdt_dis", True)
    ])
    return(debug_settings)
