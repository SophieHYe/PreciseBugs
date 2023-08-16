import logging
from logging.handlers import RotatingFileHandler
import os
import random
import re

from flask import Flask, render_template, request
from flask_wtf import FlaskForm
from wtforms import SelectField, TextAreaField

from .generate.generator import PoemMaker

pm = PoemMaker()
pm.setup()

app = Flask(__name__)
SECRET_KEY = os.urandom(32)
app.config['SECRET_KEY'] = SECRET_KEY

handler = RotatingFileHandler('poems.log', maxBytes=10000, backupCount=1)
handler.setLevel(logging.INFO)
formatter = logging.Formatter("%(asctime)s - %(name)s - %(levelname)s - %(message)s")
handler.setFormatter(formatter)
app.logger.addHandler(handler)


def alphanum(s):
    return re.sub(r'[^a-z]+', '', s.lower())


class GeneratePoemForm(FlaskForm):
    source = SelectField('Source', choices=[(k, k) for k in pm.text_sources.keys()])
    style = SelectField('Style', choices=[(k, k) for k in pm.poem_styles.keys()])


class UploadTextForm(FlaskForm):
    poem_format = SelectField('Format', choices=[(k, k) for k in pm.poem_styles.keys()])
    source_text = TextAreaField('Text', render_kw={'rows': 20, 'cols': 200})


@app.route('/', methods=['GET', 'POST'])
def generate_page():
    def valid_param(param, d):
        if param is not None:
            for k in d.keys():
                if alphanum(param) == alphanum(k):
                    return k

    form = GeneratePoemForm()

    app.logger.debug(form.validate())
    if form.errors:
        app.logger.warning(form.errors)

    if form.validate_on_submit():
        source = form.source.data
        style = form.style.data
    else:
        try:
            source_ask = request.args.get('source') or request.args.get('style')
            source_param = valid_param(source_ask, pm.text_sources)
            if source_param is not None:
                source = source_param
            else:
                source = random.choice(list(pm.text_sources.keys()))
            form.source.data = source

            style_ask = request.args.get('poem') or request.args.get('style')
            style_param = valid_param(style_ask, pm.poem_styles)
            if style_param is not None:
                style = style_param
            else:
                style = random.choice(list(pm.poem_styles.keys()))
            form.style.data = style
        except:
            app.logger.exception('Failed to select source and style')

    poem = pm.generate(source, style)
    app.logger.info(poem)
    print(poem)
    return render_template('generate.html', form=form, poem=poem)


@app.route('/custom', methods=['GET', 'POST'])
def upload():
    form = UploadTextForm()

    if form.validate_on_submit():
        print('asdf')
        source_text = form.source_text.data
        poem_format = form.poem_format.data

        form.source_text.render_kw['hidden'] = True

        try:
            poem = pm.generate_custom(source_text, poem_format)
            app.logger.info(poem)
            print(poem)
        except IndexError:
            poem="Sorry! I couldn't find a valid poem with that input. :("
        return render_template('custom_poem.html', form=form, poem=poem)

    if 'hidden' in form.source_text.render_kw:
        del form.source_text.render_kw['hidden']
    return render_template('custom.html', form=form)


if __name__ == '__main__':
    app.run()
