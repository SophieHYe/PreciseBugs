$('#save_button').click(function() {
    $('#quiz-form').submit();
});

$('#delete_button').click(function() {
    if(confirm('Deseja excluir o questionário')){
        var host = window.location.href;
        var url = host.split('?')[1];
        var action = 'index.php?' + (url.indexOf("update") == -1 ? url.replace('create','delete') : url.replace('update','delete'));
        $('#quiz-form').attr('action', action);
        $('#quiz-form').submit();
    }
});

$('#save_group_button').click(function() {
    $('#group-form').submit();
});

$('#delete_group_button').click(function() {
    if(confirm('Deseja excluir o grupo')){
        var host = window.location.href;
        var url = host.split('?')[1];
        var action = 'index.php?' + (url.indexOf("update") == -1 ? url.replace('create','delete') : url.replace('update','delete'));
        $('#group-form').attr('action', action);
        $('#group-form').submit();
    }
});

$('#save_question_group_button').click(function() {
    $('#questiongroup-form').submit();
});

$('#delete_question_group_button').click(function() {
    if(confirm('Deseja excluir o grupo')){
        var host = window.location.href;
        var url = host.split('?')[1];
        var action = 'index.php?' + (url.indexOf("update") == -1 ? url.replace('create','delete') : url.replace('update','delete'));
        $('#questiongroup-form').attr('action', action);
        $('#questiongroup-form').submit();
    }
});


$('#save_question_button').click(function() {
    $('#question-form').submit();
});

$('#delete_question_button').click(function() {
    if(confirm('Deseja excluir a questão')){
        var host = window.location.href;
        var url = host.split('?')[1];
        var action = 'index.php?' + (url.indexOf("update") == -1 ? url.replace('create','delete') : url.replace('update','delete'));
        $('#question-form').attr('action', action);
        $('#question-form').submit();
    }
});

$('#save_answer_button').click(function() {
    $('#answer-form').submit();
});

var Option = function(){
    var description = $('#QuestionOption_description');
    var answer = $('#QuestionOption_answer');
    var questionId = $('#QuestionOption_question_id');
    var id = $('#QuestionOption_id');
    var complement = $('#QuestionOption_complement');
    var container = $('#container_option');
    var button = $('#save_option_button');

    return{
        insert: function(){
            if(Option.validate()){
                var data = {description: description.val(), question_id: questionId.val(), answer: answer.val(), complement: complement.is(':checked') ? 1 : 0};
                $.ajax({
                    type: "GET",
                    url: "index.php",
                    data: { r: 'quiz/default/createOption', QuestionOption: data},
                    dataType: 'json',
                    contentType: "application/json; charset=utf-8"
                  })
                .done(function(data){
                    data = JSON.stringify(data);
                    data = DOMPurify.sanitize(data);
                    data = JSON.parse(data);
                    if(typeof data.errorCode != 'undefined' && data.errorCode == '0'){
                        var element = $('<tr></tr>')
                            .attr({'option-id': data.id, 'option-description': data.description, 'option-answer': data.answer, 'option-complement': data.complement})
                            .append($('<td></td>').text(container.find('tr').length + 1))
                            .append($('<td></td>').text(data.description))
                            .append($('<td></td>').attr({'class': 'center-button'})
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Editar').click(Option.initUpdate))
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Excluir').click(Option.initDelete))
                            );
                        container.append(element);
                        Option.clear();
                    }
                })
                .fail(function(data){
                    alert('Erro ao salvar item');
                });
            }
        },
        update: function(){
            if(Option.validate()){
                var data = {description: description.val(), question_id: questionId.val(), answer: answer.val(), complement: complement.is(':checked') ? 1 : 0};
                $.ajax({
                    type: "GET",
                    url: "index.php",
                    data: { r: 'quiz/default/updateOption', id: id.val(), QuestionOption: data},
                    dataType: 'json',
                    contentType: "application/json; charset=utf-8"
                  })
                .done(function(data){
                    data = JSON.stringify(data);
                    data = DOMPurify.sanitize(data);
                    data = JSON.parse(data);
                    if(typeof data.errorCode != 'undefined' && data.errorCode == '0'){
                       var elementActive = container.find('tr[option-id="'+data.id+'"]');
                        var element = $('<tr></tr>')
                            .attr({'option-id': data.id, 'option-description': data.description, 'option-answer': data.answer, 'option-complement': data.complement})
                            .append($('<td></td>').text(elementActive.children('td:eq(0)').text()))
                            .append($('<td></td>').text(data.description))
                            .append($('<td></td>').attr({'class': 'center-button'})
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Editar').click(Option.initUpdate))
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Excluir').click(Option.initDelete))
                            );
                        elementActive.replaceWith(element);
                    }
                    else if(typeof data.errorCode != 'undefined' && data.errorCode == '1'){
                        alert(data.msg);
                    }
                    Option.clear();
                    Option.initInsert();
                })
                .fail(function(data){
                    alert('Erro ao alterar item');
                    Option.clear();
                    Option.initInsert();
                });
            }
        },
        delete: function(){
            var data = {id: id.val(), question_id: questionId.val()};
            $.ajax({
                type: "GET",
                url: "index.php",
                data: { r: 'quiz/default/deleteOption', id: id.val(), QuestionOption: data},
                dataType: 'json',
                contentType: "application/json; charset=utf-8"
                })
            .done(function(data){
                if(typeof data.errorCode != 'undefined' && data.errorCode == '0'){
                    var elementActive = container.find('tr[option-id="'+data.id+'"]');
                    elementActive.remove();
                }
                else if(typeof data.errorCode != 'undefined' && data.errorCode == '1'){
                    alert(data.msg);
                }

                Option.clear();
                Option.initInsert();
            })
            .fail(function(data){
                alert('Erro ao excluir item');
                Option.clear();
                Option.initInsert();
            });
        },
        initInsert: function(){
            id.val('');
            button.unbind('click');
            button.bind('click', Option.insert);
        },
        initUpdate: function(e){
            e.preventDefault();
            var element = $(this).closest('tr');
            id.val(element.attr('option-id'));
            description.val(element.attr('option-description'));
            answer.val(element.attr('option-answer'));
            complement.prop('checked', Boolean(parseInt(element.attr('option-complement'))));
            button.unbind('click');
            button.bind('click', Option.update);
        },
        initDelete: function(e){
            e.preventDefault();
            var element = $(this).closest('tr');
            id.val(element.attr('option-id'));
            if(confirm('Deseja excluir o item?')){
                Option.delete();
            }
        },
        init: function(){
            id.val('');
            button.unbind('click');
            button.bind('click', Option.insert);
            Option.buildTable(dataOption);
        },
        validate: function(){
            if(description.val() == '' || answer.val() == '' || questionId.val() == '' ){
                alert('Preencha todos os campos!');
                return false;
            }
            return true;
        },
        clear: function(){
            description.val('');
            answer.val('');
            id.val('');
            complement.prop('checked',false);
        },
        buildTable: function(data){
            $.each(data, function(K, v){
                var element = $('<tr></tr>')
                            .attr({'option-id': v.id, 'option-description': v.description, 'option-answer': v.answer, 'option-complement': v.complement})
                            .append($('<td></td>').text(container.find('tr').length + 1))
                            .append($('<td></td>').text(v.description))
                            .append($('<td></td>').attr({'class': 'center-button'})
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Editar').click(Option.initUpdate))
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Excluir').click(Option.initDelete))
                            );
                container.append(element);
            });
        },
        showComplement: function(){
            var uid = $(this).attr('uid');
            var id = $(this).attr('id');
            var type = $(this).attr('type');
            var complement = id.replace("response","complement");
            var isChecked = $(this).is(':checked');
            if(type == 'checkbox'){
                if(isChecked){
                    $('#'+uid).show();
                    $('#'+complement).prop('disabled',false);
                }
                else{
                    $('#'+uid).hide();
                    $('#'+complement).prop('disabled',true);
                }
            }
            else if(type == 'radio'){
                var partialUid = uid.substring(0,(uid.length -1));
                var elementChecked = $('input[id^="'+partialUid+'"]:checked');

                if(isChecked){
                    $('div[id^="'+partialUid+'"]').each(function(){
                        $(this).hide();
                    });

                    $('input[id^="'+partialUid+'"]').each(function(){
                        if($(this).attr('type') == 'text')
                            $(this).prop('disabled',true);
                        else if($(this).attr('type') == 'radio')
                            $(this).prop('checked',false);
                    });
                    $(this).prop('checked',true);
                    $('#'+uid).show();
                    $('#'+complement).prop('disabled',false);
                }
            }
        },
        initComplement: function(){
            $('input[type="checkbox"],input[type="radio"]').each(
                function(k, v){
                    $(this).bind('click', Option.showComplement);
                    $(this).bind('change', Option.showComplement);
                    $(this).trigger('change');
                }
            );
        }
    }
}();

Option.initComplement();


var QuizQuestion = function(){
    var quizId = $('#QuizQuestion_quiz_id');
    var questionId = $('#QuizQuestion_question_id');
    var container = $('#container_quiz_question');
    var button = $('#save_quiz_question_button');

    return{
        insert: function(){
            if(QuizQuestion.validate()){
                var data = {quiz_id: quizId.val(), question_id: questionId.val()};
                $.ajax({
                    type: "GET",
                    url: "index.php",
                    data: { r: 'quiz/default/setQuizQuestion', quizId: quizId.val(), questionId: questionId.val(),  QuizQuestion: data},
                    dataType: 'json',
                    contentType: "application/json; charset=utf-8"
                  })
                .done(function(data){
                    data = JSON.stringify(data);
                    data = DOMPurify.sanitize(data);
                    data = JSON.parse(data);
                    if(typeof data.errorCode != 'undefined' && data.errorCode == '0'){
                        var element = $('<tr></tr>')
                            .attr({'key': data.quizId + '' + data.questionId, 'quiz-id': data.quizId, 'question-id': data.questionId, 'question-description': data.description})
                            .append($('<td></td>').text(container.find('tr').length + 1))
                            .append($('<td></td>').text(data.description))
                            .append($('<td></td>').attr({'class': 'center-button'})
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Excluir').click(QuizQuestion.initDelete))
                            );
                        container.append(element);
                        QuizQuestion.clear();
                    }
                    else if(typeof data.errorCode != 'undefined' && parseInt(data.errorCode) > 0){
                        alert(data.msg);
                    }
                })
                .fail(function(data){
                    alert('Erro ao salvar item');
                });
            }
        },
        delete: function(){
            var data = {quiz_id: quizId.val(), question_id: questionId.val()};
            $.ajax({
                type: "GET",
                url: "index.php",
                data: { r: 'quiz/default/unsetQuizQuestion', quizId: quizId.val(), questionId: questionId.val(), QuizQuestion: data},
                dataType: 'json',
                contentType: "application/json; charset=utf-8"
                })
            .done(function(data){
                if(typeof data.errorCode != 'undefined' && data.errorCode == '0'){
                    var elementActive = container.find('tr[key="'+data.quizId+ '' + data.questionId +'"]');
                    elementActive.remove();
                }
                else if(typeof data.errorCode != 'undefined' && data.errorCode == '1'){
                    alert(data.msg);
                }

                QuizQuestion.clear();
            })
            .fail(function(data){
                alert('Erro ao excluir item');
                QuizQuestion.clear();
            });
        },
        initInsert: function(){
            id.val('');
            button.unbind('click');
            button.bind('click', Option.insert);
        },
        initDelete: function(e){
            e.preventDefault();
            var element = $(this).closest('tr');
            quizId.val(element.attr('quiz-id'));
            questionId.val(element.attr('question-id'));
            if(confirm('Deseja excluir o item?')){
                QuizQuestion.delete();
            }
        },
        init: function(){
            questionId.val('');
            button.unbind('click');
            button.bind('click', QuizQuestion.insert);
            QuizQuestion.buildTable(dataQuizQuestion);
        },
        validate: function(){
            if(questionId.val() == '' || quizId.val() == ''){
                alert('Preencha todos os campos!');
                return false;
            }
            return true;
        },
        clear: function(){
            questionId.val('');
        },
        buildTable: function(data){
            $.each(data, function(k, v){
                var element = $('<tr></tr>')
                            .attr({'key': v.quiz_id + '' + v.question_id, 'quiz-id': v.quiz_id, 'question-id': v.question_id, 'question-description': v.description})
                            .append($('<td></td>').text(container.find('tr').length + 1))
                            .append($('<td></td>').text(v.description))
                            .append($('<td></td>').attr({'class': 'center-button'})
                                .append($('<button></button>').attr({'class': 'btn btn-primary space-button font-button'}).text('Excluir').click(QuizQuestion.initDelete))
                            );
                container.append(element);
            });
        }
    }
}();