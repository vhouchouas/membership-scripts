$(".inscription").click(function(){
    $(".match_container").show();
    $(".slack_container").hide();
});

$(".slackusers").click(function(){
    $(".match_container").hide();
    $(".slack_container").show();
    pop();
});

$.ajaxSetup({
    async: false
});

let slackUsers = null;
$.getJSON("getSlackUsers.php", function( data ) {
    slackUsers = data["members"];
});

function userprofileName(user) {
    var name = "";
    
    if(user.profile.hasOwnProperty("first_name") && user.profile.first_name.length > 0 && 
       user.profile.hasOwnProperty("last_name")  && user.profile.last_name.length > 0) {
	name += user.profile.first_name + " " + user.profile.last_name;
    } else if(user.profile.hasOwnProperty("real_name") && user.profile.real_name.length > 0) {
	name += user.profile.real_name;
    } else if(user.profile.hasOwnProperty("display_name") && user.profile.display_name.length > 0) {
	name += user.profile.display_name;
    } else if(user.hasOwnProperty("real_name")  && user.real_name.length > 0) {
	name += user.real_name;
    } else if(user.hasOwnProperty("display_name") && user.display_name.length > 0) {
	name += user.display_name;
    }

    return name;
}

function pop() {
    var slack_IDS = [];
    $(".matched[slackID]").each(function(){
	slack_IDS.push(($(this).attr("slackID")));
    });
    
    $(".slack_container tbody").children().remove();
    slackUsers.forEach((user) => {
	
	if(!user.deleted && !slack_IDS.includes(user.id) && !user.is_bot && ! user.is_app_user && user.id !== "USLACKBOT") {
	    $(`<tr><td>${user.id}</td><td>${userprofileName(user)}</td></tr>`).appendTo($($(".slack_container tbody")[0]));
	}
    });
}

function match(helloasso, row) {
    var scores = slackUsers.filter(user => {

	var score = 0;
	var count = 0;
	
	if(user.is_bot) {
	    return false;
	}
	
	if(user.profile.hasOwnProperty("email")) {
	    score += stringSimilarity.compareTwoStrings(user.profile["email"].toLowerCase(), helloasso.email.toLowerCase());
	    count +=1;
	}
	
	if(user.profile.hasOwnProperty("first_name") && user.profile.first_name.length > 0 &&
	   user.profile.hasOwnProperty("last_name") && user.profile.last_name.length > 0) {
	    score += stringSimilarity.compareTwoStrings(user.profile["first_name"].toLowerCase(), helloasso.first_name.toLowerCase())
	    score += stringSimilarity.compareTwoStrings(user.profile["last_name"].toLowerCase(), helloasso.last_name.toLowerCase());
	    count +=2;
	} else if(user.profile.hasOwnProperty("real_name") && user.profile.real_name.length > 0) {
	    score += stringSimilarity.compareTwoStrings(user.profile.real_name.toLowerCase(),
							helloasso.first_name.toLowerCase() + " " + helloasso.last_name.toLowerCase())
	    count +=1;
	} else if(user.profile.hasOwnProperty("display_name") && user.profile.display_name.length > 0) {
	    score += stringSimilarity.compareTwoStrings(user.profile["display_name"].toLowerCase(),
							helloasso.first_name.toLowerCase() + " " + helloasso.last_name.toLowerCase())
	    count +=1;
	} else if(user.hasOwnProperty("real_name") && user["real_name"].length > 0) {
	    score += stringSimilarity.compareTwoStrings(user["real_name"].toLowerCase(),
							helloasso.first_name.toLowerCase() + " " + helloasso.last_name.toLowerCase())
	    count +=1;
	} else if(user.hasOwnProperty("display_name") && user["display_name"].length > 0) {
	    score += stringSimilarity.compareTwoStrings(user["display_name"].toLowerCase(),
							helloasso.first_name.toLowerCase() + " " + helloasso.last_name.toLowerCase())
	    count +=1;
	} else {
	    console.log("User profile is not complete.");
	    console.log(user);
	    return false;
	}

	user.score = score/(1.*count);
	return true;
    });
    
    scores.sort(function(a, b) {
	return a.score < b.score;
    });
    
    scores.slice(0, 3).forEach((user) => {
	var slack = $(`<div similarity="${user.score}" slackID="${user.id}" class='col-3 border-bottom border-2 border-dark'></div>`).appendTo(row);
	var color = `rgba(0,255,0,${Math.pow(user.score,2)})`;
	slack.css('background-color', color);
	
	if(user.deleted) {
	    slack.css("background", `repeating-linear-gradient(
  -45deg,
  #ffffff,
  #ffffff 10px,
  ${color} 10px,
  ${color} 20px
)`);
	}

	
	$(slack).append(
	    user["id"] + "</br>" + 
		userprofileName(user) + "</br>" + 
		user.profile["email"]
	);
	
	slack.click(function() {
	    var slackID = $(this).attr("slackID");
	    var helloassoID = $($($(this).parent()).children()[0]).attr("helloassoID");
	    
	    if(!$(this).hasClass("matched")) {
		$(this).css('background-color', "rgba(0,0,255,0.4)");
		$(this).addClass("matched");
		$($(this).parent()).children("[slackID]").not(".matched").remove();
		$(`[slackID=${slackID}]`).not(this).hide();
	    } else {		
		var parent = $(this).parent();
		$($(this).parent()).children("[slackID]").remove();
		match(helloasso_inscriptions[helloassoID], parent);
		$(`[slackID=${slackID}]`).not(this).show();
	    }
	});
    });
}

let helloasso_inscriptions = null;
$.getJSON( "MAWhoNeedToRenew.php?json", function( data ) {
    helloasso_inscriptions = data;
});
				   
$.each( helloasso_inscriptions, function( key, helloasso ) {
    var row = $("<div class='row'></div>").appendTo($(".match_container"));
    var helloasso_col = $(`<div helloassoID="${key}" class='col-3 border-bottom border-2 border-dark'></div>`).appendTo(row);
    
    $(helloasso_col).append(
	helloasso.event_date + "</br>" + 
	    helloasso.first_name + " " + helloasso.last_name + "</br>" + 
	    helloasso.email);
    
    match(helloasso, row);
});
