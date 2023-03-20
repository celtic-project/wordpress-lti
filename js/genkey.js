function lti_tool_verify(isadd) {

  var validated = true;

  if (document.lti_tool_addlti.lti_tool_name.value == "") {
    document.getElementById("lti_tool_name_text").style.color = "red";
    validated = false;
  } else {
    document.getElementById("lti_tool_name_text").style.color = "";
  }

  if (!isadd) {
    if (document.lti_tool_addlti.lti_tool_secret.value == "") {
      document.getElementById("lti_tool_secret_text").style.color = "red";
      validated = false;
    } else {
      document.getElementById("lti_tool_secret_text").style.color = "";
    }
  } else if (!document.querySelector('input[name="lti_tool_scope"]:checked') || (document.querySelector('input[name="lti_tool_scope"]:checked').value == "")) {
    document.getElementById("lti_tool_scope_text").style.color = "red";
    validated = false;
  }

  if (!validated) {
    document.getElementById('lti_tool_error').style.display = 'block';
  }

  return validated;
}
