def get_wlan_config():
    return(dict([
        ('ssid','strommesser.ch'),
        ('pw','6d65737365725057')
    ]))

def get_device_config():
    return(dict([
        ('userid','3'),
        ('post_key','X6O4wqb339L3w9Fmjl9kPVAkcwXkVhTj'),
        ('local_ip','192.168.178.58') # make sure this does not change (e.g. on router). gplug:192.168.178.58, whatwatt:192.168.178.47
    ]))   

# first value is the normal, non-debug value for standard functionality
def get_debug_settings():
    return(dict([
        ('wlan','real'),       # can be 'real'|'simulated'. When simulated, json_data must be 'file'
        ('sleep','normal'),    # can be 'normal'|'short'
        ('json_data','file'),  # can be 'local_net'|'web'|'file'. Latter two are for debug
        ('server_txrx',False)   # can be True|False. Whether meas data are sent to the server and settings received (every 2 mins)
    ]))
