function createConsumer(url) {

  var validated = true;
  var hidden = false;

  document.getElementById("lti_name_text").style.color = "";
  document.getElementById("req1").style.color = "";

  if (document.addlti.lti_name.value == "") {
    document.getElementById("lti_name_text").style.color = "red";
    document.getElementById("req1").style.color = "red";
    return false;
  }

  if (validated) {

	  var xmlhttp = false;
	  var xmlhttppost = false;

	  try {
      xmlhttp = new XMLHttpRequest();
		  xmlhttppost = new XMLHttpRequest();
    } catch (failed) {
 		  alert("Error initializing XMLHttpRequest!");
		  return false;
    }

    // Get the scope setting
    var lti_scope = 3;
	  var radios = document.getElementsByName("lti_scope");
	  for (i = 0; i < radios.length; i++) {
		  if (radios[i].checked) lti_scope = radios[i].value;
	  }

		// Generate a key and secret
		var getUrl = url + "/lti/includes/GenKeySecret.php?lti_scope=" + lti_scope;
		xmlhttp.open("GET", getUrl, true);
  	xmlhttp.send();
    xmlhttp.onreadystatechange=function() {
			if (xmlhttp.readyState==4 && xmlhttp.status==200) {
				var myJSONObject = JSON.parse(xmlhttp.responseText);

				// Now post the data to create the consumer in the DB
				xmlhttppost.open("POST", url + '/lti/includes/DoAddLTIConsumer.php', true);
				xmlhttppost.onreadystatechange = function () {
				  if (xmlhttppost.readyState == 4 && xmlhttppost.status == 200) {
				    document.getElementById('key').innerHTML = myJSONObject.Key ;
		        document.getElementById('secret').innerHTML = myJSONObject.Secret;
						document.getElementById('lti_title').innerHTML = document.addlti.lti_name.value;
						document.getElementById('ltikey').value = myJSONObject.Key;

				    // Hide the page elements no longer needed
				    document.getElementById('form').style.display = 'none';

				    // Show the element for the LTI consumer
		        document.getElementById('keySecret').style.display = 'block';
					}
				}
				xmlhttppost.setRequestHeader("Content-type","application/x-www-form-urlencoded");

				var postdata =
			  "lti_name=" + document.addlti.lti_name.value +
				"&lti_key=" + myJSONObject.Key +
				"&lti_secret=" + myJSONObject.Secret +
				"&lti_protected=" + document.addlti.lti_protected.checked +
        "&lti_enabled=" + document.addlti.lti_enabled.checked +
				"&lti_enable_from=" + document.addlti.lti_enable_from.value +
				"&lti_enable_until=" + document.addlti.lti_enable_until.value +
				"&_wpnonce_add_lti=" + document.addlti._wpnonce_add_lti.value;

				xmlhttppost.send(postdata);
      }
	  }
	}
  return false;
}

function verify() {
	var validated = true;

	if (document.addlti.lti_name.value == "") {
    document.getElementById("lti_name_text").style.color = "red";
    document.getElementById("req1").style.color = "red";
    validated = false;
  }  else {
	  document.getElementById("lti_name_text").style.color = "black";
    document.getElementById("req1").style.color = "black";
	}

	if (document.addlti.lti_secret.value == "") {
    document.getElementById("lti_secret_text").style.color = "red";
    document.getElementById("req3").style.color = "red";
    validated = false;
  } else {
	  document.getElementById("lti_secret_text").style.color = "black";
    document.getElementById("req3").style.color = "black";
	}

  return validated;
}