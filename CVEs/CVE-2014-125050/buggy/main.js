var nunjucks = require('nunjucks'); //package
var express = require('express');
var path = require('path');
//var request = require('request');
var mysql = require('mysql');
var async = require('async');
var bodyparser = require('body-parser'); // request body to hash;detect name attribute in html file
var app = express();

//configuration values
var MIN_ITEM_AMOUNT = 2;
var MAX_ITEM_AMOUNT = 10;
var MIN_QUESTION_AMOUNT = 1;
var MAX_QUESTION_AMOUNT = 10;

app.use(bodyparser.urlencoded({
  extended:true
}));

nunjucks.configure('views', {
  autoescape: true,
  express: app
});

app.use('/public', express.static(path.join(__dirname, 'public'))); //can use files in /public directory

var connection = mysql.createConnection({
  host    : 'localhost',
  user    : 'root',
  password: '1112',
  database: 'voter'
});

connection.connect();

app.get('/admin/categories', function(req, res) {// / is website page, and has nothing to do with file path

  var msg = req.query.msge; //get msge from quesry string in url, ?msge=...
  connection.query('SELECT * FROM Section WHERE status <> 0', function(err, rows, fields){

    if (!err) {
      res.render('sections.html', {
        data: rows,
        message: msg
      });
    } else {
      res.send(err);
    }
  });

});

app.get('/', function(req, res) {// / is website page, and has nothing to do with file path

  var msg = req.query.msge; //get msge from quesry string in url, ?msge=...
  connection.query('SELECT * FROM Section WHERE status <> 0', function(err, rows, fields){

    if (!err) {
      res.render('categories.html', {
        data: rows,
        message: msg
      });
    } else {
      res.send(err);
    }
  });

});

app.get('/section/:id', function(req, res) {
  var id = req.params.id;
  var query = connection.query('SELECT Section.id, Section.name AS secname, Section.description AS secdesc, Survey.title AS stitle, Survey.description AS sdesc, Survey.id AS sid, Survey.holder from Section,Survey WHERE Survey.sectionId=Section.id AND Section.status=1 AND Survey.status=1 AND sectionId='+id, function(err, rows, fields) {
    if (!err) {
      res.render('section.html', {
        data: rows,
        defaultId: id
      });
    } else {
      res.status(500).send(err);
    }
  });
});

/*  test: When select a survey, show all questions and items from the survey
*/
app.all('/survey/:id', function(req, res) {
  var id = req.params.id;
  var sql ='SELECT Survey.id as sid, Survey.title AS stitle, Survey.description AS sdesc, Question.id AS qid, Item.id AS iid,question, item from Survey, Question, Item where Survey.id=' + id +' AND Survey.status=1 AND Question.status=1 AND Item.status=1 AND Question.surveyId=Survey.id AND Item.questionId=Question.id ORDER BY qid,iid;';

  if (req.method == 'GET') {
    var query = connection.query(sql, function(err, rows, fields) {
      if (!err) {
        if(rows.length == 0) {
          res.status(404).send('Survey ' + id + ' is not found');
        } else {
          res.render('survey.html',{
            data: hashfyQuery(rows)
          });
        }
      } else {
        res.send(err);
      }
    });
  }
  else if (req.method == 'POST') {
    var body = req.body;
    var itemIdtoUpdate =[];
    for (var key in body) {
      itemIdtoUpdate.push(body[key]);
    }

    var cnt = 0;
    async.series({
      countQuestion: function(callback) {
        connection.query('SELECT COUNT(*) as cntQuestion from Question WHERE Question.status=1 AND surveyId='+id, function(err, rows, field) {
          if (!err) {
            if (rows.length == 0) {
              callback({code: 404, msg: 'Survey not found'});
            } else {
              cnt = rows[0].cntQuestion;
              callback(null);
            }
          } else {
            callback({code: 500, msg: err});
          }
        });
      },
      post: function(callback) {
        if (Object.keys(body).length < cnt) {
          var query = connection.query(sql, function(err, rows, fields) {
            if (!err) {
              res.render('survey.html', {
                msg: 'you have questions unfilled',
                cache: body,
                data: hashfyQuery(rows)
              });
              callback(null);
            } else {
              callback({code: 500, msg: err});
            }
          });
        }
        else {
          var updateSql = 'UPDATE Item SET count=count+1 WHERE id IN ('+itemIdtoUpdate.join(',') + ')';
          var query = connection.query(updateSql, function(err, rows, field) {
            if(!err) {
              res.redirect('/result/'+id);//display
              callback(null);
            } else {
              callback({code: 500, msg: err});
            }
          });
        }
      }
    }, function(err){
      if (err) {
        res.status(err.code).send(err.msg);
      }
    });
  }
});

app.get('/result/:id', function(req, res) {
  var id = req.params.id;
  var sql ='SELECT Survey.id as sid, Survey.title AS stitle, Survey.description AS sdesc, Survey.sectionId AS categoryId, Question.id AS qid, Item.id AS iid,question, item, Item.count AS icnt from Survey, Question, Item where Survey.id=' + id +' AND Survey.status=1 AND Question.status=1 AND Item.status=1 AND Question.surveyId=Survey.id AND Item.questionId=Question.id ORDER BY qid,iid;';

  var query = connection.query(sql, function(err, rows, fields) {
    if (!err) {
      if(rows.length == 0) {
        res.status(404).send('Survey ' + id + ' is not found');
      } else {
        res.render('result.html',{
          data: hashfyQuery(rows)
        });
      }
    } else {
      res.send(err);
    }
  });
});

app.all('/surveys/add', function(req, res) {
  var defaultId = req.query.defaultid;
  var invalidCatMsg = null;

  //validate defaultId
  if (defaultId == parseInt(defaultId, 10)) {
    invalidCatMsg = null;
  } else {
    invalidCatMsg = 'We find you are using an invalid category.';
  }

  if (req.method == 'GET') {
    var body = req.body;

    connection.query('SELECT * FROM Section WHERE status <> 0 ORDER BY Section.name', function(err, rows, fields){

      if (!err) {
        if (rows.length == 0) {
          res.status(404).send('There are no sections. Please contact Admin to create a section first.'); //if query result is empty, return 404 page
        }
        else {
          if (invalidCatMsg) {
            res.send(invalidCatMsg);
          } else {
            res.render('add-survey-form.html', {
              categories: rows,
              defaultId: defaultId
            });
          }
        }
      } else {
        res.send(err);
      }
    });
  }
  else if (req.method == 'POST') {
    var body = req.body;
    var survey = JSON.parse(body.surveyJSON);
    var surveyId = null;
    var questionId = null;

    //validation part
    var hasErr = false;
    if (survey.stitle == '' || survey.stitle == null) {
      survey.titleExcept = 'Please fill in title.';
      hasErr = true;
    }
    else{
      if (survey.category == '' || survey.category == null) {
        survey.categoryExcept = 'Please select a category.';
        hasErr = true;
      }
      var cntQuestion = survey.questions.length;
      if (cntQuestion < MIN_QUESTION_AMOUNT || cntQuestion > MAX_QUESTION_AMOUNT) {
        survey.cntQuestionExcept = 'Question amount between 1 and 10.';
        hasErr = true;
      }
      for (var i = 0; i < survey.questions.length; i++) {
        var q = survey.questions[i];
        if ((q.question == '' || q.question == null)) {
          survey.questions[i].questionExcept = 'Please fill in question.';
          hasErr = true;
        }

        var cntItem = q.items.length;
        if (cntItem < MIN_ITEM_AMOUNT || cntItem > MAX_ITEM_AMOUNT) {
          survey.questions[i].cntItemExcept = 'Item amount between 2 and 10.';
          hasErr = true;
        }
        for (var j = 0; j < q.items.length; j++) {
          var it = q.items[j];
          if ((it.item == '' || it.item == null)) {
            survey.questions[i].items[j].itemExcept = 'Please fill in item.';
            hasErr = true;
          }
        }
      }
    }

    if(hasErr) {

      return connection.query('SELECT * FROM Section WHERE status <> 0 ORDER BY Section.name', function(err, rows, fields){
        if (!err) {
          if (rows.length == 0) {
            res.status(404).send('There are no sections. Please contact Admin to create a section first.'); //if query result is empty, return 404 page
          }
          else {
            if (invalidCatMsg) {
              res.send(invalidCatMsg);
            } else {
              res.render('add-survey-form.html', {
                categories: rows,
                defaultId: defaultId,
                data: survey,
                msg: "Some input errors"
              });
            }
          }
        } else {
          res.send(err);
        }
      });
    }
    async.series({
      createSurvey: function(callback) {
        var sql = 'INSERT INTO Survey(title, description, holder, sectionId) VALUES(\''
        + survey.stitle + '\',\''
        + survey.sdesc + '\',\''
        + survey.holder + '\','
        + survey.category
        + ');';
        var query = connection.query(sql, function(err, rows, fields) {
          if (!err) {
            surveyId = rows.insertId;
          }
          callback(err);
        });
      },

      createQuestion: function(callback) {
        async.eachSeries(survey.questions, function(questionHash, questionArrCallback){
          var questionSql = 'INSERT INTO Question(question, surveyId) VALUES(\''
          + questionHash.question + '\','
          + surveyId
          + ');';
          async.series({
            createQuestion: function(questionCallback) {
              var addQuestionQuery = connection.query(questionSql, function(questionErr, questionRows, questionFields) {
                if (!questionErr) {
                  questionId = questionRows.insertId;
                }
                questionCallback(questionErr);
              });
            },

            createItem: function(itemArrCallback) {
              async.eachSeries(questionHash.items, function(item, itemCallback){
                var itemSql = 'INSERT INTO Item(item, questionId) VALUES(\''
                + item.item +'\','
                + questionId
                + ');';
                var addItemQuery = connection.query(itemSql, function(itemErr, itemRows, itemFields) {
                  if (!itemErr) {
                  }
                  itemCallback(itemErr);
                });
              },
              function(itemEachSeriesErr){
                if (!itemEachSeriesErr) {
                }
                itemArrCallback(itemEachSeriesErr);
              });
            }
          }, questionArrCallback); //end async series in createQuestion
        }, function(questionEachSeriesErr){
          if (!questionEachSeriesErr) {
          }
          callback(questionEachSeriesErr);
        }); //end async eachSeries in createQuestion
      } // end createQuestion task
    },

    function(err) {
      if (err) {
        res.status(500).send(err);
      } else {
        res.send('success page');
      }
    }); //end async series in POST
  } //end POST
});

app.get('/admin/category/delete/:id', function(req, res) {
  var id = req.params.id;
  var msg = 'delete suceessfully.';
  var query = connection.query('UPDATE Section SET status=0 WHERE id=' + id, function(err, rows, fields) {
    if (!err) {
      res.redirect('/admin/categories?msge='+msg);
    } else {
      res.status(500).send(err);
    }
  });
});

function hashfyQuery(rows) {
  var res = {};
  res.sid = rows[0].sid;//surveyId
  res.stitle = rows[0].stitle;
  res.sdesc = rows[0].sdesc;
  res.holder = 'admin'; //to be changed
  res.category = rows[0].categoryId;//to be changed
  res.titleExcept = null;
  res.cntQuestionExcept = null;

  var questions = [];
  for (var i = 0; i < rows.length; i++) {
    if (i == 0 || rows[i].qid != rows[i - 1].qid) {
      var q = {};
      q.qid = rows[i].qid;
      q.question = rows[i].question;
      q.items = [];
      q.questionExcept = null;
      q.cntItemExcept = null;

      var total = 0;
      var j = i;
      while (j < rows.length && rows[j].qid == rows[i].qid) {
        total += rows[j].icnt;
        j++;
      }
      q.total = total;

      var it = {};
      it.iid = rows[i].iid;
      it.item = rows[i].item;
      it.icnt = rows[i].icnt;
      it.percent = (q.total > 0) ? Math.round(it.icnt / q.total * 100) : 0;
      q.items.push(it);

      questions.push(q);
    } else {
      var q = questions[questions.length - 1];
      var it = {};
      it.iid = rows[i].iid;
      it.item = rows[i].item;
      it.icnt = rows[i].icnt;
      it.percent = (q.total > 0) ? Math.round(it.icnt / q.total * 100) : 0;
      it.itemExcept = null;
      q.items.push(it);
    }
  }
  res.questions = questions;
  return res;
}

//edit a survey based on id
app.all('/surveys/edit/:id', function(req, res) {
  var id = req.params.id;

  if ( req.method == 'GET') {
    var categories = {};
    async.series({
      getCategories: function(callback) {
        var sql = 'SELECT * FROM Section WHERE status <> 0 ORDER BY Section.name';
        connection.query(sql, function(err, rows, field) {
          if (!err) {
            if (rows.length == 0) {
              callback({code: 404, msg: 'There are no categories. Please contact Admin to create a category.'});
            } else {
              categories = rows;
              callback(null);
            }
          } else {
            callback({code: 500, msg: err});
          }
        });
      },
      get: function(callback) {
        var sql = 'SELECT Section.id AS categoryId, Survey.id AS sid, Survey.title AS stitle, Survey.description as sdesc, Question.id AS qid, question, Item.id AS iid, item from Section,Survey,Question,Item where Survey.id=' + id +' AND Survey.status=1 AND Question.status=1 And Item.status=1 AND Section.id=Survey.sectionId AND Question.surveyId=Survey.id AND Item.questionId=Question.id ORDER BY qid,iid;';
        var query = connection.query(sql, function(err, rows, fields) {
          if (!err) {
            if(rows.length == 0) {
              callback({code: 404, msg: 'Survey ' + id + ' is not found'});
            } else {
              res.render('edit-survey-form.html',{
                data: hashfyQuery(rows),
                categories: categories,
              });
              callback(null);
            }
          } else {
            callback({code: 500, msg: err});
          }
        });
      }
    }, function(err){
      if (err) {
        res.status(err.code).send(err.msg);
      }
    }); //end async.series
  }//end get
  else if (req.method == 'POST') {
    var body = req.body;
    var survey = JSON.parse(body.surveyJSON);
    survey.sid = id;
    var surveyId;

    //validation part
    var hasErr = false;
    if (survey.stitle == '' || survey.stitle == null) {
      survey.titleExcept = 'Please fill in title.';
      hasErr = true;
    }
    else{
      if (survey.category == '' || survey.category == null) {
        survey.categoryExcept = 'Please select a category.';
        hasErr = true;
      }
      var cntQuestion = survey.questions.length;
      for (var index = 0; index < survey.questions.length; index++) {
        if (survey.questions[index].qDelete == '1')
          cntQuestion--;
      }
      if (cntQuestion < MIN_QUESTION_AMOUNT || cntQuestion > MAX_QUESTION_AMOUNT) {
        survey.cntQuestionExcept = 'Question amount between ' + MIN_QUESTION_AMOUNT+' and ' + MAX_QUESTION_AMOUNT + '.';
        hasErr = true;
      }
      for (var i = 0; i < survey.questions.length; i++) {
        var q = survey.questions[i];
        if ((q.question == '' || q.question == null) && q.qDelete != '1') {
          survey.questions[i].questionExcept = 'Please fill in question.';
          hasErr = true;
        }

        var cntItem = q.items.length;
        for (var index = 0; index < q.items.length; index++) {
          if (q.items[index].itemDelete == '1')
            cntItem--;
        }
        if (cntItem < MIN_ITEM_AMOUNT || cntItem > MAX_ITEM_AMOUNT) {
          survey.questions[i].cntItemExcept = 'Item amount between ' + MIN_ITEM_AMOUNT + ' and ' + MAX_ITEM_AMOUNT + '.';
          hasErr = true;
        }
        for (var j = 0; j < q.items.length; j++) {
          var it = q.items[j];
          if ((it.item == '' || it.item == null) && it.itemDelete != '1') {
            survey.questions[i].items[j].itemExcept = 'Please fill in item.';
            hasErr = true;
          }
        }
      }
    }
    if(hasErr) {
      return connection.query('SELECT * FROM Section WHERE status <> 0 ORDER BY Section.name', function(err, rows, fields){
        if (!err) {
          if (rows.length == 0) {
            res.status(404).send('There are no sections. Please contact Admin to create a section first.'); //if query result is empty, return 404 page
          }
          else {
            res.render('edit-survey-form.html', {
              categories: rows,
              data: survey,
              msg: "Some input errors"
            });
          }
        } else {
          res.send(err);
        }
      });
    }
    async.series({
      createSurvey: function(callback) {
        var sql;
        if (survey.sid) {
          sql = 'UPDATE Survey SET title=\''+survey.stitle+'\', description=\''+survey.sdesc+'\', sectionId='+ survey.category +' WHERE id='+id+';';
          surveyId = id;
        } else {
          surveyId = null;
          sql = 'INSERT INTO Survey(title, description, holder, sectionId) VALUES(\''
          + survey.stitle + '\',\''
          + survey.sdesc + '\',\''
          + survey.holder + '\','
          + survey.category
          + ');';
        }
        var query = connection.query(sql, function(err, rows, fields) {
          if (!err) {
            if(surveyId == null)
              surveyId = rows.insertId;
          }
          callback(err);
        });
      },

      createQuestion: function(callback) {
        async.eachSeries(survey.questions, function(questionHash, questionArrCallback){
          var questionSql;
          var questionId;
          if (questionHash.qid == null || questionHash.qid == undefined) {
            questionId = null;
            questionSql = 'INSERT INTO Question(question, surveyId) VALUES(\''
            + questionHash.question + '\','
            + surveyId
            + ');';
          } else {
            if (questionHash.qDelete == '1') {
              questionSql = 'UPDATE Question SET status=0 WHERE id='+questionHash.qid+';';
            } else {
              questionSql = 'UPDATE Question SET question=\''+questionHash.question+'\' WHERE id='+questionHash.qid+';';
            }
            questionId = questionHash.qid;
          }
          async.series({
            createQuestion: function(questionCallback) {
              var addQuestionQuery = connection.query(questionSql, function(questionErr, questionRows, questionFields) {
                if (!questionErr) {
                  if (questionId == null)
                    questionId = questionRows.insertId;
                }
                questionCallback(questionErr);
              });
            },

            createItem: function(itemArrCallback) {
              async.eachSeries(questionHash.items, function(item, itemCallback){
                var itemSql;

                if(item.iid == undefined || item.iid == null) {
                  itemSql = 'INSERT INTO Item(item, questionId) VALUES(\''
                  + item.item +'\','
                  + questionId
                  + ');';
                } else {
                  if (item.itemDelete == '1') {
                    itemSql = 'UPDATE Item SET status=0 WHERE id='+item.iid+';';
                  } else {
                    itemSql = 'UPDATE Item SET item=\''+item.item+'\' WHERE id='+item.iid+';';
                  }
                }
                var addItemQuery = connection.query(itemSql, function(itemErr, itemRows, itemFields) {
                  if (!itemErr) {
                  }
                  itemCallback(itemErr);
                });
              }, function(itemEachSeriesErr){
                if (!itemEachSeriesErr) {
                }
                itemArrCallback(itemEachSeriesErr);
              });
            }
          }, questionArrCallback); //end async series in createQuestion
        },
        function(questionEachSeriesErr){
          if (!questionEachSeriesErr) {
          }
          callback(questionEachSeriesErr);
        }); //end async eachSeries in createQuestion
      } // end createQuestion task
    },

    function(err) {
      if (err) {
        res.send(err);
      } else {
        res.send('success page');
      }
    }); //end async series in POST
  }
});

app.all('/admin/categories/edit/:id', function(req, res) { //:id means the parameter in this part of url is called 'id'
  var id = req.params.id; //get the 'id' part from the url, not from qurey string; from query string use req.query.'...'
  if (req.method == 'GET') {//req default method is GET
    connection.query('SELECT * FROM Section WHERE id='+id, function(err, rows, fields){
      if (!err) {
        if (rows.length > 0) {
          res.render('edit-section-form.html', {
            data: rows[0]
          });
        } else {
          res.status(404).send('not found'); //if query result is empty, return 404 page
        }
      } else {
        res.send(err);
      }
    });
  }
  else if (req.method == 'POST') {//can be set method to POST in html file form tag
    var body = req.body;//a hashtable with names and values got from html tag with 'name' attribute
    body.id = id;
    if (body.name == '' || body.name == undefined) {
      res.render('edit-section-form.html', {
        data: body,
        except: 'no name input'
      });
    }
    else {
      var msg = 'edit successfully';
      var query = connection.query('UPDATE Section SET ? where id='+id, body, function(err, rows, fields){ // this will automatic match table column names with names in body, and change values
        if (!err) {
          res.redirect('/admin/categories?msge='+msg);//make msg a part of query string so that req.query.msge will find msg
        } else {
          res.send(err);
        }
      });
    }
  }
});

/* problem: when input name is '', it should not post
*/
app.all('/admin/categories/add', function(req, res){
  if (req.method == 'GET') {
    res.render('add-section-form.html');
  }
  else if (req.method == 'POST') {

    var body = req.body;

    if (body.name == '' || body.name === undefined) {
      res.render('add-section-form.html', {
        except: 'no name',
        cache: body
      });
    }
    else {
      var msg = 'add successfully';
      var values = '\'' + body.name + '\',\'' + body.description + '\','+ 1;
      var query = connection.query('INSERT INTO Section(name, description, status) VALUES('+values+')', function(err, rows, fields) {
        if (!err) {
          res.redirect('/admin/categories?msge='+msg);
        } else {
          res.status(500).send(err);
        }
      });
    }
  }
});

var server = app.listen(3000, function() {
  var host = server.address().address;
  var port = server.address().port;
  console.log('Now listening at: ' + host + ':' + port);
});
