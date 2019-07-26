import requests
import sys
import base64
import json

url = "{server}/{key}/"
push_server = "https://bark.charlesjoe.com.cn"  # 此处填写服务端地址
push_key = "bvanDXRpGWf3r9GGJZbbzE"             # 此处填写自己的设备识别码


if len(sys.argv) != 2:
    print("参数错误！[{}]".format(len(sys.argv)))
else:
    baseCode = sys.argv[1]
    print(baseCode)
    decode = base64.b64decode(baseCode).decode("utf-8")
    jsonObj = json.loads(decode)
     
    req_url = url.format(server=push_server, key=push_key)
    r = requests.post(url=req_url, data=jsonObj, headers={'Content-Type':'application/x-www-form-urlencoded;charset=utf-8'})
    print("======================")