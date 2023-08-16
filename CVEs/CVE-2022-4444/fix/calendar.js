$(document).on("click", ".new-calendar-button", function () {
    $(".error-calendar-event").hide();
    $(".create-calendar-title, #copy").val("");
    $("#stages").val(null).trigger("change.select2");
    $("#myNewCalendar").modal("show");
});

$(document).on("click", ".create-calendar", function () {
    var form = $(this).closest("form");
    if (form.find(".create-calendar-title").val() === "" || form.find("#stages").val() === null) {
        form.find(".alert").html("Campos com * são obrigatórios.").show();
    } else {
        form.find(".alert").hide();
        $.ajax({
            url: "?r=calendar/default/create",
            type: "POST",
            data: {
                title: $(".create-calendar-title").val(),
                stages: $("#stages").val(),
                copyFrom: $("#copy").val()
            },
            beforeSend: function () {
                $("#myNewCalendar .centered-loading-gif").show();
                $("#myNewCalendar .modal-body").css("opacity", 0.3).css("pointer-events", "none");
                $("#myNewCalendar button").attr("disabled", "disabled");
            },
        }).success(function (data) {
            data = JSON.parse(data);
            if (!data.valid) {
                form.find(".alert").html(DOMPurify.sanitize(data.error)).show();
                $("#myNewCalendar .centered-loading-gif").hide();
                $("#myNewCalendar .modal-body").css("opacity", 1).css("pointer-events", "auto");
                $("#myNewCalendar button").removeAttr("disabled");
            } else {
                window.location.reload();
            }
        });
    }
});

$(document).on("click", ".edit-calendar-title", function (e) {
    e.stopPropagation();
    $("#edit-calendar-title-modal").find("#Calendar_id").val($(this).attr("data-id"));
    $("#edit-calendar-title-modal").find("#Calendar_title").val($(this).closest(".accordion-group").find(".accordion-title").text().trim());
    $("#edit-calendar-title-modal").modal("show");
});

$(document).on("click", ".edit-calendar-title-button", function () {
    if ($("#edit-calendar-title-modal").find("#Calendar_title").val() === "") {
        $("#edit-calendar-title-modal").find(".alert").html("Preencha o campo abaixo.").show();
    } else {
        $("#edit-calendar-title-modal").find(".alert").hide();
        $.ajax({
            url: "?r=calendar/default/editCalendarTitle",
            type: "POST",
            data: {
                id: $("#edit-calendar-title-modal").find("#Calendar_id").val(),
                title: $("#edit-calendar-title-modal").find("#Calendar_title").val()
            },
            beforeSend: function () {
                $("#edit-calendar-title-modal").find(".modal-body").css("opacity", 0.3).css("pointer-events", "none");
                $("#edit-calendar-title-modal").find("button").attr("disabled", "disabled");
                $("#edit-calendar-title-modal").find(".centered-loading-gif").show();
            },
        }).success(function (data) {
            data = JSON.parse(data);
            if (data.valid) {
                $(".calendar-container[data-id=" + $("#edit-calendar-title-modal").find("#Calendar_id").val() + "]").closest(".accordion-group").find(".accordion-title").text($("#edit-calendar-title-modal").find("#Calendar_title").val());
                $("#edit-calendar-title-modal").modal("hide");
            } else {
                $("#edit-calendar-title-modal").find(".alert").html(DOMPurify.sanitize(data.error)).show();
            }
        }).complete(function () {
            $("#edit-calendar-title-modal").find(".modal-body").css("opacity", 1).css("pointer-events", "auto");
            $("#edit-calendar-title-modal").find("button").removeAttr("disabled");
            $("#edit-calendar-title-modal").find(".centered-loading-gif").hide();
        });
    }
});

$(document).on("click", ".remove-calendar", function (e) {
    e.stopPropagation();
    $("#calendar_removal_id").val($(this).data('id'));
    $("#removeCalendar").modal("show");
});

$(document).on("click", ".remove-calendar-button", function () {
    $("#removeCalendar").find(".alert").hide();
    $.ajax({
        url: "?r=calendar/default/removeCalendar",
        type: "POST",
        data: {
            calendar_removal_id: $("#calendar_removal_id").val(),
        },
        beforeSend: function () {
            $("#removeCalendar .centered-loading-gif").show();
            $("#removeCalendar .modal-body").css("opacity", 0.3).css("pointer-events", "none");
            $("#removeCalendar button").attr("disabled", "disabled");
        },
    }).success(function (data) {
        data = JSON.parse(data);
        if (data.valid) {
            $(".calendar-container[data-id=" + $("#calendar_removal_id").val() + "]").closest(".accordion-group").remove();
            $(".accordion").children().length
                ? $(".no-calendars-alert").hide()
                : $(".no-calendars-alert").show();
            $("#removeCalendar").modal("hide");
        } else {
            $("#removeCalendar").find(".alert").html(DOMPurify.sanitize(data.error)).show();
        }
    }).complete(function () {
        $("#removeCalendar .centered-loading-gif").hide();
        $("#removeCalendar .modal-body").css("opacity", 1).css("pointer-events", "auto");
        $("#removeCalendar button").removeAttr("disabled");
    });
});

$(document).on("click", ".remove-event-button", function () {
    $.ajax({
        url: "?r=calendar/default/deleteEvent",
        type: "POST",
        data: {
            id: $("#CalendarEvent_id").val(),
            calendarId: $("#CalendarEvent_calendar_fk").val()
        },
        beforeSend: function () {
            $("#myChangeEvent .centered-loading-gif").show();
            $("#myChangeEvent .modal-body").css("opacity", 0.3).css("pointer-events", "none");
            $("#myChangeEvent button").attr("disabled", "disabled");
        },
    }).success(function (data) {
        data = JSON.parse(data);
        if (data.valid) {
            var eventDays = $("a.change-event[data-id=" + data.id + "]");
            eventDays.attr("data-id", "-1").removeAttr("data-toggle").removeAttr("data-placement").removeAttr("data-original-title");
            eventDays.parent().removeClass("calendar-" + data.color);
            eventDays.find(".calendar-icon").remove();
            $("#myChangeEvent").modal("hide");
        } else {
            $("#myChangeEvent").find(".alert").html(DOMPurify.sanitize(data.error)).show();
        }
    }).complete(function () {
        $("#myChangeEvent .centered-loading-gif").hide();
        $("#myChangeEvent .modal-body").css("opacity", 1).css("pointer-events", "auto");
        $("#myChangeEvent button").removeAttr("disabled");
    });
});

$(document).on("click", ".change-event", function () {
    var event = this;
    $("#myChangeEvent").find(".selected-calendar-current-year").val($(event).closest(".calendar").data("year"));
    $(".error-calendar-event").hide();
    if ($(event).attr('data-id') !== "-1") {
        $(".remove-event-button").show();
        $.ajax({
            url: "?r=calendar/default/event",
            type: "POST",
            dataType: 'text',
            data: {
                id: $(event).attr('data-id')
            },
            beforeSend: function () {
                $(event).closest(".calendar-container").css("pointer-events", "none").css("opacity", 0.3);
                $(event).closest(".accordion-inner").find(".centered-loading-gif").show();
            },
            success: function (data) {
                data = $.parseJSON(data);
                $("#CalendarEvent_id").val(data.id);
                $("#CalendarEvent_calendar_fk").val(data.calendar_fk);
                $("#CalendarEvent_name").val(data.name);
                $("#CalendarEvent_start_date").val(data.start_date.split(" ")[0]);
                $("#CalendarEvent_end_date").val(data.end_date.split(" ")[0]);
                $("#CalendarEvent_calendar_event_type_fk").val(data.calendar_event_type_fk);
                $("#CalendarEvent_copyable").attr("checked", data.copyable);
                $("#myChangeEvent").modal("show");
            },
            complete: function () {
                $(event).closest(".calendar-container").css("pointer-events", "auto").css("opacity", 1);
                $(event).closest(".accordion-inner").find(".centered-loading-gif").hide();
            }
        });
    } else {
        $(".remove-event-button").hide();
        $("#CalendarEvent_id").val($(event).attr('data-id'));
        $("#CalendarEvent_calendar_fk").val($(event).closest(".calendar").attr("data-id"));
        $("#CalendarEvent_name").val("");
        $("#CalendarEvent_start_date").val($(event).data('year') + "-" + pad($(event).data('month'), 2) + "-" + pad($(event).data('day'), 2));
        $("#CalendarEvent_end_date").val($(event).data('year') + "-" + pad($(event).data('month'), 2) + "-" + pad($(event).data('day'), 2));
        $("#CalendarEvent_calendar_event_type_fk").val("");
        $("#CalendarEvent_copyable").attr("checked", "checked");
        $("#myChangeEvent").modal("show");
    }
});

$(document).on("click", ".save-event", function (e) {
    var form = $(this).closest("form");
    if (form.find("#CalendarEvent_name").val() === "" || form.find("#CalendarEvent_start_date").val() === "" || form.find("#CalendarEvent_end_date").val() === "" || form.find("#CalendarEvent_calendar_event_type_fk").val() === "") {
        form.find(".alert").html("Campos com * são obrigatórios.").show();
    } else if (form.find("#CalendarEvent_end_date").val() < form.find("#CalendarEvent_start_date").val()) {
        form.find(".alert").html("A Data de Encerramento não deve ser anterior à Data de Início.").show();
    } else if (form.find("#CalendarEvent_start_date").val().split("-")[0] !== form.find(".selected-calendar-current-year").val() || form.find("#CalendarEvent_end_date").val().split("-")[0] !== form.find(".selected-calendar-current-year").val()) {
        form.find(".alert").html("O intervalo de datas deve atender o ano do calendário.").show();
    } else {
        form.find(".alert").hide();
        $.ajax({
            url: "?r=calendar/default/changeEvent",
            type: "POST",
            data: {
                id: $("#CalendarEvent_id").val(),
                calendarFk: $("#CalendarEvent_calendar_fk").val(),
                name: $("#CalendarEvent_name").val(),
                startDate: $("#CalendarEvent_start_date").val(),
                endDate: $("#CalendarEvent_end_date").val(),
                eventTypeFk: $("#CalendarEvent_calendar_event_type_fk").val(),
                copyable: $("#CalendarEvent_copyable").is(":checked")
            },
            beforeSend: function () {
                $("#myChangeEvent .centered-loading-gif").show();
                $("#myChangeEvent .modal-body").css("opacity", 0.3).css("pointer-events", "none");
                $("#myChangeEvent button").attr("disabled", "disabled");
            },
        }).success(function (data) {
            data = JSON.parse(data);
            if (data.valid) {
                var calendar = $(".calendar-container[data-id=" + $("#CalendarEvent_calendar_fk").val() + "]");
                if (data.uniqueDayToDelete !== null) {
                    var uniqueDay = $("a.change-event[data-id=" + data.uniqueDayToDelete.id + "]");
                    uniqueDay.attr("data-id", "-1").removeAttr("data-toggle").removeAttr("data-placement").removeAttr("data-original-title");
                    uniqueDay.parent().removeClass("calendar-" + data.uniqueDayToDelete.color);
                    uniqueDay.find(".calendar-icon").remove();
                }
                var oldEventDays = $("a.change-event[data-id=" + data.eventId + "]");
                oldEventDays.attr("data-id", "-1").removeAttr("data-toggle").removeAttr("data-placement").removeAttr("data-original-title");
                oldEventDays.parent().removeClass("calendar-" + data.color);
                oldEventDays.find(".calendar-icon").remove();
                $.each(data.datesToFill, function () {
                    var date = calendar.find(".change-event[data-year=" + this.year + "][data-month=" + this.month + "][data-day=" + this.day + "]");
                    date.attr("data-id", data.eventId).attr("data-toggle", "tooltip").attr("data-placement", "top").attr("data-original-title", data.eventName);
                    date.parent().addClass("calendar-" + data.color);
                    date.children(".calendar-icon").remove();
                    date.prepend("<i class='calendar-icon fa " + DOMPurify.sanitize(data.icon) + "'></i>");
                    $("#myChangeEvent").modal("hide");
                });
                $('[data-toggle="tooltip"]').tooltip({container: "body"});
            } else {
                form.find(".alert").html(DOMPurify.sanitize(data.error)).show();
            }
        }).complete(function () {
            $("#myChangeEvent .centered-loading-gif").hide();
            $("#myChangeEvent .modal-body").css("opacity", 1).css("pointer-events", "auto");
            $("#myChangeEvent button").removeAttr("disabled");
        });
    }
});

$(document).on("click", ".show-stages", function (e) {
    var icon = this;
    e.stopPropagation();
    if (!$(icon).closest(".accordion-group").find(".floating-stages-container").length) {
        $(".floating-stages-container").remove();
        $.ajax({
            url: "?r=calendar/default/showStages",
            type: "POST",
            data: {
                id: $(icon).data('id')
            },
            beforeSend: function () {
                $(icon).css("pointer-events", "none").find("i").addClass("fa-spin").addClass("fa-spinner").removeClass("fa-question-circle-o");
            },
        }).success(function (data) {
            data = JSON.parse(data);
            var html = "<div class='floating-stages-container'><div class='stages-container-title'>" + $(icon).closest(".accordion-group").find(".accordion-title").text() + "</div>";
            $.each(data, function () {
                html += "<div class='stage-container'>" + this.name + "</div>";
            });
            html += '<i class="close-stages-container fa fa-remove"></i></div>';
            $(icon).closest(".accordion-group").append(html);
            $(".floating-stages-container").css("top", $(icon).closest(".accordion-group").offset().top);
        }).complete(function () {
            $(icon).css("pointer-events", "auto").find("i").removeClass("fa-spin").removeClass("fa-spinner").addClass("fa-question-circle-o");
        });
    } else {
        $(".floating-stages-container").remove();
    }

});

$(document).on("click", ".close-stages-container", function () {
    $(".floating-stages-container").remove();
});

$(document).on("click", ".change-calendar-status", function (e) {
    e.stopPropagation();
    $("#changeCalendarStatus").find(".alert").hide();
    $("#changeCalendarStatus").find(".modal-title").text($(this).find("i").hasClass("fa-eye") ? "Indisponibilizar Calendário" : "Disponibilizar Calendário");
    $("#changeCalendarStatus").find("#calendar-change-status-id").val($(this).data('id'));
    $("#changeCalendarStatus").modal("show");
});

$(document).on("click", ".change-calendar-status-button", function () {
    $("#changeCalendarStatus").find(".alert").hide();
    $.ajax({
        url: "?r=calendar/default/changeCalendarStatus",
        type: "POST",
        data: {
            id: $("#calendar-change-status-id").val(),
        },
        beforeSend: function () {
            $("#changeCalendarStatus .centered-loading-gif").show();
            $("#changeCalendarStatus .modal-body").css("opacity", 0.3).css("pointer-events", "none");
            $("#changeCalendarStatus button").attr("disabled", "disabled");
        },
    }).success(function (data) {
        data = JSON.parse(data);
        if (data.valid) {
            var icon = $(".change-calendar-status[data-id=" + $("#calendar-change-status-id").val() + "]");
            data.available
                ? icon.attr("data-original-title", "Indisponibilizar Calendário").find("i").removeClass("fa-eye-slash").addClass("fa-eye")
                : icon.attr("data-original-title", "Disponibilizar Calendário").find("i").removeClass("fa-eye").addClass("fa-eye-slash");
            $(icon).tooltip({container: "body"});
            $("#changeCalendarStatus").modal("hide");
        } else {
            $("#changeCalendarStatus").find(".alert").html(DOMPurify.sanitize(data.error)).show();
        }
    }).complete(function () {
        $("#changeCalendarStatus .centered-loading-gif").hide();
        $("#changeCalendarStatus .modal-body").css("opacity", 1).css("pointer-events", "auto");
        $("#changeCalendarStatus button").removeAttr("disabled");
    });
});

$(document).on("click", ".add-fundamental-menor", function () {
    $("#stages option[value=14]").prop("selected", true);
    $("#stages option[value=15]").prop("selected", true);
    $("#stages option[value=16]").prop("selected", true);
    $("#stages").trigger("change.select2");
});

$(document).on("click", ".add-fundamental-maior", function () {
    $("#stages option[value=17]").prop("selected", true);
    $("#stages option[value=18]").prop("selected", true);
    $("#stages option[value=19]").prop("selected", true);
    $("#stages option[value=20]").prop("selected", true);
    $("#stages option[value=21]").prop("selected", true);
    $("#stages option[value=41]").prop("selected", true);
    $("#stages").trigger("change.select2");
});

$(document).on("click", ".remove-stages", function () {
    $("#stages option").prop("selected", false);
    $("#stages").trigger("change.select2");
});

function pad(num, size) {
    num = num.toString();
    while (num.length < size) num = "0" + num;
    return num;
}