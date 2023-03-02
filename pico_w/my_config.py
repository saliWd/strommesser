def get_wlan_config():
    config_wlan = dict([
        ("ssid","strommesser"),
        ("pw","publicPW")
    ])
    return(config_wlan)

def get_device_config():
    config_device = dict([
        ("userid","3"),
        ("post_key","X6O4wqb339L3w9Fmjl9kPVAkcwXkVhTj")
    ])
    return(config_device)   

def get_debug_settings():
    debug_settings = dict([
        ("print",True),
        ("ir_sim",True),
        ("wlan_sim",False),
        ("sleep",True)
    ])
    return(debug_settings)
