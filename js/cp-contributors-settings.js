document.addEventListener("DOMContentLoaded", function(){
	let fromTag = document.getElementById('from_tag'),
		toTag   = document.getElementById('to_tag')

	cpc_change_from_tag()

	fromTag.addEventListener('change', cpc_change_from_tag)
	toTag.addEventListener('change', cpc_change_to_tag)
});

function cpc_change_to_tag() {
	let fromTag = document.getElementById('from_tag'),
		toTag   = document.getElementById('to_tag')

	var options = fromTag.getElementsByTagName("option")

	for (var i = 0; i < options.length; i++) {
	(i <= (toTag.selectedIndex - 1))
		? options[i].disabled = true
		: options[i].disabled = false
	}
}

function cpc_change_from_tag() {
	let fromTag = document.getElementById('from_tag'),
		toTag   = document.getElementById('to_tag')

	var options = toTag.getElementsByTagName("option")

	for (var i = 0; i < options.length; i++) {
	(i > fromTag.selectedIndex)
		? options[i].disabled = true
		: options[i].disabled = false
	}
}
