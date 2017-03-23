var config = {
    map: {
        '*': {
            "fngrtouch": 'Payapi_Catalog/fngrsharesdk/touch.min',
            "fngrui": 'Payapi_Catalog/fngrsharesdk/sdk-ui.min',
            "fngrsdk": 'Payapi_Catalog/fngrsharesdk/sdk-controller.min'
        }
    },
    shim: {
        'fngrtouch': {
            deps: ['jquery']
        } ,
        'fngrui':  {
            deps: ['jquery','fngrtouch']
        },
        'fngrsdk':  {
            deps: ['jquery','fngtouch','fngrui']
        }
    }
};