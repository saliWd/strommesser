def get_wlan_config():
    config_wlan = dict([
        ("ssid","strommesser.ch"),
        ("pw","messerPW")
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
        ('print',True),       # can be True|False
        ('wlan','simulated'), # can be 'real'|'simulated'. When simulated, json_data must be 'file'
        ('sleep','short'),    # can be 'normal'|'short'
        ('json_data','file')  # can be 'local_net'|'web'|'file'. Latter two are for debug
    ])
    return(debug_settings)

def debug_jdata():
    jdata = {'system': {'id': 'ECC9FF5C80B0', 'boot_id': '3D5B5E1A', 'time_since_boot': 20815, 'date_time': '2025-04-14T20:53:12Z'}, 'meter': {'interface': 'MBUS', 'vendor': 'Landis+Gyr', 'prefix': 'LGZ', 'protocol': 'DLMS', 'id': '72913313', 'status': 'OK'}, 'report': {'date_time': '2025-04-14T21:53:10Z', 'energy': {'reactive': {'exported': {'inductive': {'total': 65.067}, 'capacitive': {'total': 99.656}}, 'imported': {'inductive': {'total': 147.264}, 'capacitive': {'total': 10.076}}}, 'active': {'positive': {'t1': 127.896, 't2': 35.355, 'total': 163.26}, 'negative': {'t1': 92.135, 't2': 414.198, 'total': 506.341}}}, 'interval': 5.238, 'conv_factor': 1, 'id': 4524, 'instantaneous_power': {'active': {'positive': {'total': 0.001}, 'negative': {'total': 0}}}}}
    return jdata

