import ast
import base64
import io
import logging
import random
from html import escape
from os import remove

import magic
from flask import Flask, abort, request
from PIL import Image

app = Flask(__name__)
app.logger.setLevel(logging.DEBUG)
app.config["MAX_CONTENT_LENGTH"] = 2 * (10**5)
# allow a maximum of 200kb length. no image is (usually) larger than 200kb, so this is good.


def get_config():
    f = open("config", "r")
    conf = ast.literal_eval(f.read())
    f.close()

    cache_size = conf["max_cache_size"]
    host = conf["host"]
    port = conf["port"]

    return cache_size, host, port


def allowed_file(enc_data):
    mimetype = magic.from_buffer(enc_data, mime=True)

    if mimetype[:5] != "image":
        return enc_data, False
    else:
        return enc_data, True


@app.route("/")
def index():
    return "<h1>401 - Unauthorised</h1>"


@app.errorhandler(404)
def errorhandle(e):
    return abort(401)


@app.route("/Y2hlY2tfY2FjaGVkX2ZpbGVz", methods=["POST"])
def check_cache():
    # Explanation on Caching
    # -- BV
    # Originally, I was going to have the script cache here, where it actually checks the cache. However,
    # I soon realised that the all_files file is only modified in the other function to add to the file.
    # Because of this, we only really need to check the cache status there, and see if it is over the
    # cache limit in that function. The only thing we have to do here in relation to caching is moving items
    # to the end of the array.

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
        print(
            "Not provided a dict, instead a "
            + str(typeprov)
            + " was provided. Returning NoneType."
        )
        return "None"

    for key in db:
        print(f"Analysing data {data} against item(s) {key[0]}.")
        if data == key[0]:
            print("Found match for " + str(data) + " in database.")

            # caching
            new_data = []
            for y in db:
                if y[0] == data:
                    x = y
                else:
                    new_data.append(y)
            new_data.append(x)

            f = open("all_files", "w")
            f.write(str(new_data))
            f.close()

            print("Returning dictionary: " + str(key))
            return str(key)

    print("No match found. Returning NoneType.")
    return "None"


@app.route("/bGVhdmVfcmlnaHRfbm93", methods=["POST"])
def uploadimage():
    # print(request.json)
    if not request.json or "image" not in request.json:
        print("No data sent or no image provided. Aborting with 400.")
        abort(400)

    im_b64 = request.json["image"]
    img_bytes = base64.b64decode(im_b64.encode("utf-8"))
    img_bytes, valid = allowed_file(img_bytes)
    if not valid:
        return escape({"entry": "False"})
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

    while file_name is None or file_name in all_files:
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
    if title[:9] == "[PAUSED] ":
        title = title[9::]

    singer = request.json["singer"]
    album = request.json["album"]

    file_db_entry = [
        {"title": title, "singer": singer, "album": album},
        file_name,
        file_ending,
    ]
    print(f"New db entry: {file_db_entry}")

    all_files.append(file_db_entry)

    # caching
    # we want a limit of X amount of files as defined by the config

    # 1. see how long the list is
    # 2. if it is over get_config()'s cache limit, delete value [0]
    # 3. delete it on disk.

    cache, x, y = get_config()
    del x
    del y

    length = len(all_files)
    while (
        length > cache
    ):  # if it is not over the limit, it will skip. if it is, it does this.
        # if we have gone over our cache limit, let's delete the first entry.
        filename = all_files[0][1] + all_files[0][2]
        remove(filename)
        del all_files[0]
        length = len(all_files)

    f = open("all_files", "w")
    f.write(str(all_files))
    f.close()

    file_name = file_name + file_ending

    img.save(file_name)

    print(f"Saved {file_name} from {file_db_entry}.")
    print(f"Returning {file_db_entry}.")
    return escape(str({"entry": file_db_entry}))


def run_server_api():
    cache, host, port = get_config()
    app.run(host=host, port=port)


if __name__ == "__main__":
    run_server_api()
