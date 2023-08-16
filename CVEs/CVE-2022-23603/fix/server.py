import io                 
import base64                  
import logging
import random
import ast
from PIL import Image

from flask import Flask, request, abort
from html import escape

app = Flask(__name__)          
app.logger.setLevel(logging.DEBUG)

@app.route("/")
def index():
    return "<h1>401 - Unauthorised</h1>"

@app.errorhandler(404)
def errorhandle(e):
    return abort(401)

@app.route("/Y2hlY2tfY2FjaGVkX2ZpbGVz", methods=['POST'])
def check_cache():
    f = open("all_files", "r")
    db = ast.literal_eval(f.read())
    f.close()

    tmp = request.json
    x = str({"title": tmp["title"], "singer": tmp["singer"], "album": tmp["album"]})
    data = ast.literal_eval(x)

    if data["title"][:9] == "[PAUSED] ":
        data["title"] = data["title"][9::]

    del tmp

    if type(data) != dict:
        typeprov = type(data)
        print("Not provided a dict, instead a "+str(typeprov)+" was provided. Returning NoneType.")
        return "None"

    for key in db:
        print(f"Analysing data {data} against item(s) {key[0]}.")
        if data == key[0]:
            print("Found match for " + str(data) + " in database.")
            print("Returning dictionary: " + str(key))
            return str(key)

    print("No match found. Returning NoneType.")
    return "None"

@app.route("/bGVhdmVfcmlnaHRfbm93", methods=['POST'])
def uploadimage():
    #print(request.json)   
    if not request.json or 'image' not in request.json:
        print("No data sent or no image provided. Aborting with 400.")
        abort(400)
             
    im_b64 = request.json['image']
    img_bytes = base64.b64decode(im_b64.encode('utf-8'))
    img = Image.open(io.BytesIO(img_bytes))

    file_ending = img.format
    print(f"File has filetype {file_ending}.")

    if file_ending == "JPEG":
        file_ending = ".jpg"
    else:
        file_ending = ".png"

    one_hundred_million = 100000000
    lots_of_nine = 999999999

    file_name = None

    f = open("all_files", "r")
    all_files = ast.literal_eval(f.read())
    f.close()

    attempt = 0

    while file_name == None or file_name in all_files:
        if attempt <= 1000:
            file_name = random.randint(one_hundred_million, lots_of_nine)

            file_name = base64.b64encode(str(file_name).encode("utf-8")).decode("utf-8")

            print(f"Trying new file name: {file_name}")
        else:
            attempt = 0
            one_hundred_million += 100000
            lots_of_nine += 1000000

            while one_hundred_million >= lots_of_nine:
                one_hundred_million -= 10000

            one_hundred_million -= 10000

    print(f"Successful file name: {file_name}")

    title = request.json["title"]
    print("First 9 chars of title: "+str(title[:9]))
    print("Title from 10th char: "+str(title[9::]))
    if title[:9] == "[PAUSED] ":
        title = title[9::]

    singer = request.json["singer"]
    album = request.json["album"]
    
    file_db_entry = [{"title": title, "singer": singer, "album": album}, file_name, file_ending]
    print(f"New db entry: {file_db_entry}")

    all_files.append(file_db_entry)

    f = open("all_files", "w")
    f.write(str(all_files))
    f.close()

    file_name = file_name + file_ending

    image = img.save(file_name)
    print(f"Saved {file_name} from {file_db_entry}.")
    print(f"Returning {file_db_entry}.")
    return escape({"entry": file_db_entry})
  
  
def run_server_api():
    app.run(host='0.0.0.0', port=7873)
  
  
if __name__ == "__main__":     
    run_server_api()
