function lti_tool_create_platform(url) {

  document.getElementById("lti_tool_name_text").style.color = "";
  document.getElementById("lti_tool_scope_text").style.color = "";

  var validated = true;
  if (document.lti_tool_addlti.lti_tool_name.value == "") {
    document.getElementById("lti_tool_name_text").style.color = "red";
    validated = false;
  }
  if (!document.querySelector('input[name="lti_tool_scope"]:checked') || (document.querySelector('input[name="lti_tool_scope"]:checked').value == "")) {
    document.getElementById("lti_tool_scope_text").style.color = "red";
    validated = false;
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
    var radios = document.getElementsByName("lti_tool_scope");
    for (i = 0; i < radios.length; i++) {
      if (radios[i].checked)
        lti_scope = radios[i].value;
    }

    // Generate a key and secret
    var getUrl = url + "lti_tool_genkeysecret&lti_scope=" + lti_scope;
    xmlhttp.open("GET", getUrl, true);
    xmlhttp.send();
    xmlhttp.onreadystatechange = function () {
      if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
        var myJSONObject = JSON.parse(xmlhttp.responseText);

        // Now post the data to create the platform in the DB
        xmlhttppost.open("POST", url + 'lti_tool_addplatform', true);
        xmlhttppost.onreadystatechange = function () {
          if (xmlhttppost.readyState == 4 && xmlhttppost.status == 200) {
            document.getElementById('lti_tool_key').innerHTML = myJSONObject.Key;
            document.getElementById('lti_tool_secret').innerHTML = myJSONObject.Secret;
            document.getElementById('lti_tool_title').innerHTML = document.lti_tool_addlti.lti_tool_name.value;
            document.getElementById('lti_tool_download_key').value = myJSONObject.Key;

            // Hide the page elements no longer needed
            document.getElementById('lti_tool_form').style.display = 'none';

            // Show the element for the LTI platform
            document.getElementById('lti_tool_keysecret').style.display = 'block';
          }
        }
        xmlhttppost.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        var postdata =
                "lti_tool_name=" + document.lti_tool_addlti.lti_tool_name.value +
                "&lti_tool_key=" + myJSONObject.Key +
                "&lti_tool_secret=" + myJSONObject.Secret +
                "&lti_tool_protected=" + document.lti_tool_addlti.lti_tool_protected.checked +
                "&lti_tool_enabled=" + document.lti_tool_addlti.lti_tool_enabled.checked +
                "&lti_tool_enable_from=" + document.lti_tool_addlti.lti_tool_enable_from.value +
                "&lti_tool_enable_until=" + document.lti_tool_addlti.lti_tool_enable_until.value +
                "&lti_tool_scope=" + lti_scope +
                "&lti_tool_debug=" + document.lti_tool_addlti.lti_tool_debug.checked +
                "&lti_tool_platformid=" + document.lti_tool_addlti.lti_tool_platformid.value +
                "&lti_tool_clientid=" + document.lti_tool_addlti.lti_tool_clientid.value +
                "&lti_tool_deploymentid=" + document.lti_tool_addlti.lti_tool_deploymentid.value +
                "&lti_tool_authorizationserverid=" + document.lti_tool_addlti.lti_tool_authorizationserverid.value +
                "&lti_tool_authenticationurl=" + document.lti_tool_addlti.lti_tool_authenticationurl.value +
                "&lti_tool_accesstokenurl=" + document.lti_tool_addlti.lti_tool_accesstokenurl.value +
                "&lti_tool_jku=" + document.lti_tool_addlti.lti_tool_jku.value +
                "&lti_tool_rsakey=" + document.lti_tool_addlti.lti_tool_rsakey.value +
                "&lti_tool_role_staff=" + document.lti_tool_addlti.lti_tool_role_staff.value +
                "&lti_tool_role_student=" + document.lti_tool_addlti.lti_tool_role_student.value +
                "&lti_tool_role_other=" + document.lti_tool_addlti.lti_tool_role_other.value +
                "&_wpnonce_add_lti_tool=" + document.lti_tool_addlti._wpnonce_add_lti_tool.value;

        xmlhttppost.send(postdata);
      }
    }
  } else {
    document.getElementById('lti_tool_error').style.display = 'block';
  }

  return false;
}

function lti_tool_verify() {

  var validated = true;

  document.getElementById("lti_tool_name_text").style.color = "";
  document.getElementById("lti_tool_secret_text").style.color = "";

  if (document.lti_tool_addlti.lti_tool_name.value == "") {
    document.getElementById("lti_tool_name_text").style.color = "red";
    validated = false;
  }

  if (document.lti_tool_addlti.lti_tool_secret.value == "") {
    document.getElementById("lti_tool_secret_text").style.color = "red";
    validated = false;
  }

  if (!validated) {
    document.getElementById('lti_tool_error').style.display = 'block';
  }

  return validated;
}
