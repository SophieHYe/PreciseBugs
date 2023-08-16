def try_get_cached(domain, dict):
    title = dict["title"]
    singer = dict["singer"]
    album = dict["album"]

    import requests, json

    api = f'http://{domain}:7873/Y2hlY2tfY2FjaGVkX2ZpbGVz'

    headers = {'Content-type': 'application/json', 'Accept': 'text/plain'}
    payload = json.dumps({"title": title, "singer": singer, "album": album})
    response = requests.post(api, data=payload, headers=headers)

    status = eval(response.text)

    return status


def get(image_file, domain, title, singer, album):
    import base64
    import json    
    import ast               

    import requests

    api = f'http://{domain}:7873/bGVhdmVfcmlnaHRfbm93'

    with open(image_file, "rb") as f:
        im_bytes = f.read()        
    im_b64 = base64.b64encode(im_bytes).decode("utf8")

    headers = {'Content-type': 'application/json', 'Accept': 'text/plain'}

    status = try_get_cached(domain, {"title": title, "singer": singer, "album": album})
    status = ast.literal_eval(str(status))

    if status == None:
        print("Cached version not found. Uploading image with song metadata.")
        payload = json.dumps({"image": im_b64, "title": title, "singer": singer, "album": album})
        response = requests.post(api, data=payload, headers=headers)

        data = response.text["data"]
    else:
        data = status

    # data = [{"title": title, "singer": singer, "album": album}, file_name, file_ending]

    return data

#print(get("sample_image.jpg", "localhost", "title", "artist", "album"))

#try_get_cached("localhost", {"title": "title", "singer": "singer", "album": "album"})

#print(get("sample_image.png", "localhost", "not_title", "singer", "album"))