// On page load, clear some structures, grab nav data,
// and then get and display current matches.
$(function () {
	$.when(
		getNav()
	).then(getAndDisplayCurrentMatches);
});

function capitalize(string) {
	return string.charAt(0).toUpperCase() + string.slice(1);
}

function getParameterByName(nameTok) {
	var nameTokArray = nameTok + "[]";
	var query = window.location.search.substring(1);
	var vars = query.split("&");
	var arrayVal = [];
	var textStyle = 0;
	for (var i = 0; i < vars.length; i++) {
		// Look for our parameter, nameTok.  If the parameter has
		// the form nameTok[]=foo, or if it appears more than once,
		// then return an array of values.  Otherwise, if
		// nameTok=foo appears, return foo.  Otherwise, return the empty
		// string.
		var pair = vars[i].split("=");
		if (pair.length != 2) {
			continue;
		}
		var val = pair[1];
		if (pair[0] == nameTok) {
			textStyle = 1;
			arrayVal.push(val);
		} else if (pair[0] == nameTokArray) {
			arrayVal.push(val);
		}
	}
	if (arrayVal.length == 0) {
		return "";
	} else if (textStyle &&
		arrayVal.length == 1) {
		return arrayVal[0]; // Return single value as text.
	} else {
		return arrayVal; // Return array values as an array.
	}
}

function removeLastDirectoryPartOf(the_url) {
	var the_arr = the_url.split('/');
	the_arr.pop();
	return (the_arr.join('/'));
}

var chugCountColorClasses = ["text-success", "text-danger", "text-warning"];

function getColorForCount(curCount, chugMin, chugMax) {
	var colorClass = chugCountColorClasses[0];
	if (curCount > chugMax &&
		chugMax > 0) {
		colorClass = chugCountColorClasses[1];
	} else if (curCount < chugMin) {
		colorClass = chugCountColorClasses[2];
	}
	return colorClass;
}

// Loop through all chugim in a group, and update their
// current count and associated count color.
function updateCount(chugId2Beta, curChugHolder) {
	var groupHolder = $(curChugHolder).closest(".groupholder");
	var chugHolders = $(groupHolder).children(".chugholder");
	$(chugHolders).each(function (index) {
		var chugId = $(this).attr('name');
		var newCount = $(this).find("ul div li").length;
		var min = parseInt(chugId2Beta[chugId]["min_size"]);
		var max = parseInt(chugId2Beta[chugId]["max_size"]);
		var colorClass = getColorForCount(newCount, min, max);
		var curCountHolder = $(this).find("span[name=curCountHolder]");
		$.each(chugCountColorClasses, function (index, classToRemove) {
			// Remove old color class.
			$(curCountHolder).removeClass(classToRemove);
		});
		// Add new color class and count.
		$(curCountHolder).attr('value', newCount);
		$(curCountHolder).text("cur = " + newCount);
		$(curCountHolder).addClass(colorClass);
	});
}

function sortedGroupIdKeys(groupId2ChugId2MatchedCampers, groupIdSorted) {
	// Populate the sorted list.
	var present = new Array();
	for (var groupId in groupId2ChugId2MatchedCampers) {
		present.push(groupId);
	}
	
	// Only include the groupIds which we have chugim for
	groupIdSorted.forEach((id) => {
		if(present.includes(toString(id))) {
			groupIdSorted = groupIdSorted.filter(item => item !== id);
		}
	});

	return groupIdSorted;
}

function chugIdsSortedByName(chugId2Beta, chugId2Entity) {
	// Populate the sorted list.
	var sorted = new Array();
	for (var chugId in chugId2Entity) {
		if (chugId2Entity.hasOwnProperty(chugId)) { // to be safe
			sorted.push(chugId);
		}
	}
	// Do the actual sort, by chug name and then group name.
	sorted.sort(function (x, y) {
		var betaX = chugId2Beta[x];
		var betaY = chugId2Beta[y];
		if (betaX.name.toLowerCase() < betaY.name.toLowerCase()) {
			return -1;
		}
		if (betaX.name.toLowerCase() > betaY.name.toLowerCase()) {
			return 1;
		}
		if (betaX.group_name.toLowerCase() < betaY.group_name.toLowerCase()) {
			return -1;
		}
		if (betaX.group_name.toLowerCase() > betaY.group_name.toLowerCase()) {
			return 1;
		}
		return 0;
	});

	return sorted;
}

function isDupOf(droppedChugId, matchHash, deDupMatrix, chugId2MatchedCampers, matchedCamperId) {
	if (droppedChugId in chugId2MatchedCampers) {
		// If the camper is being dropped into their current mapping (e.g., being
		// dragged back), do not flag that as a duplicate.
		campersOriginallyMatched = chugId2MatchedCampers[droppedChugId];
		for (var i = 0; i < campersOriginallyMatched.length; i++) {
			if (campersOriginallyMatched[i] == matchedCamperId) {
				return -1;
			}
		}
	}
	if (droppedChugId in deDupMatrix) {
		var forbiddenToDupSet = deDupMatrix[droppedChugId];
		for (var matchedChugId in matchHash) {
			if (matchedChugId == droppedChugId) {
				// Don't flag our own ID as a dup: it will be in the hash.
				continue;
			}
			if (matchedChugId in forbiddenToDupSet) {
				return matchedChugId;
			}
		}
	}
	return -1;
}

function getNav() {
	$.ajax({
		url: 'ajax.php',
		type: 'post',
		data: { get_nav: 1 },
		success: function (txt) {
			$("#nav").html(txt);
		}
	});
}

function doAssignmentAjax(action, title, errText,
	edah_ids, group_ids, block) {
	var values = {};
	values[action] = 1;
	values["edah_ids"] = edah_ids;
	values["group_ids"] = group_ids;
	values["block"] = block;
	$.ajax({
		url: 'levelingAjax.php',
		async: false,
		type: 'post',
		data: values,
		success: function (data) {
			if (action == "reassign") {
				// Fade and then reload with new data (for multiple clicks).
				$("#results:visible").removeAttr("style").fadeOut();
			}
		},
		error: function (xhr, desc, err) {
			errMsg = "The system was unable to ";
			errMsg += errText;
			errMsg += ". If the problem persists, please contact the administrator.  Error: ";
			errMsg += err + " " + desc;
			$("#results").text(errMsg);
			$("#results").show("slide", 250);
		}
	});
}

// Updates the "chugim with free space" box at top of page
// This function executes on page load and after a save (as capacity numbers change),
// refreshing the box at those times
function displayChugimWithSpace(edah_ids, group_ids, block) {
	$.ajax({
		url: 'levelingAjax.php',
		type: 'post',
		async: false,
		data: {
			matches_and_prefs: 1,
			edah_ids: edah_ids,
			group_ids: group_ids,
			block_id: block
		},
		success: function (json) {
			// general info
			var groupId2Name = json["groupId2Name"];
			var groupIdSorted = json["groupIdSorted"];
			var edahId2Name = json["edahId2Name"];
			var chugId2Beta = json["chugId2Beta"];
			var groupId2ChugId2MatchedCampers = json["groupId2ChugId2MatchedCampers"];
			var chugId2FreeSpace = {};

			// freeHtml is the HTML which will be output - a mega accordion containing another accordion
			// which says how much space is left in chugim
			var freeHtml = "<div class=\"accordion\" id=\"chugimMegaFreeSpaceAccordion\"><div class=\"accordion-item\"><h2 class=\"accordion-header\" id=\"chugimSpaceHeader\">"
				+ "<button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#collapseSpace\" aria-expanded=\"false\" aria-controls=\"collapseSpace\">"
				+ capitalize(json['chugimTerm']) + " with Free Space</h4></button></h2><div id=\"collapseSpace\" class=\"accordion-collapse collapse\" aria-labelledby=\"chugimSpaceHeader\" data-bs-parent=\"#chugimMegaFreeSpaceAccordion\">"
				+ "<div class=\"accordion-body\">";
			freeHtml += "<h4>Expand to view " + json['chugimTerm'] + " with free space</h4>";

			// add the accordion which will be for for each chug group
			freeHtml += "<div class=\"accordion\" id=\"chugimFreeSpaceAccordion\">";
	
			// go through each chug group
			var sortedGroupIds = sortedGroupIdKeys(groupId2ChugId2MatchedCampers, groupIdSorted);
			for (var j = 0; j < sortedGroupIds.length; j++) {
				var groupId = sortedGroupIds[j];
				var chugId2MatchedCampers = groupId2ChugId2MatchedCampers[groupId];
				var sortedChugIds = chugIdsSortedByName(chugId2Beta, chugId2MatchedCampers);
				freeHtml += "<div class=\"accordion-item\"><h2 class=\"accordion-header\" id=\"group_" + groupId + "\">"
				+ "<button class=\"inner accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#collapseGroup_" + groupId + "\" aria-expanded=\"false\" aria-controls=\"collapseGroup_" + groupId + "\">"
				+ groupId2Name[groupId] + "</h4></button></h2><div id=\"collapseGroup_" + groupId + "\" class=\"accordion-collapse collapse\" aria-labelledby=\"group_" + groupId + "\" data-bs-parent=\"#chugimFreeSpaceAccordion\">"
				+ "<div class=\"accordion-body\">";
				// see amount of space in each chug in the group
				for (var i = 0; i < sortedChugIds.length; i++) {
					var chugId = sortedChugIds[i];
					if (chugId == -1) {
						continue
					}
					var matchedCampers = chugId2MatchedCampers[chugId];
					var curCount = matchedCampers.length;
					// Add a chug holder, and put camper holders inside it.
					var freeSpace;
					var name = chugId2Beta[chugId]["name"];
					var chugMin = chugId2Beta[chugId]["min_size"];
					var chugMax = chugId2Beta[chugId]["max_size"];
					if (chugMax == "0" || chugMax == 0 || chugMax == "10000" || chugMax == 10000 || 
						chugMax === null || (typeof (chugMax) === 'undefined')) {
						freeSpace = "unlimited";
						freeHtml += name + ": " + freeSpace + " space<br>";
					} else if ((!(chugId in chugId2FreeSpace)) && chugMax > curCount) {
						freeSpace = chugMax - curCount;
						var sp = (freeSpace == 1 || freeSpace == "unlimited") ? "space" : "spaces";
						freeHtml += name + ": " + freeSpace + " " + sp + " left<br>";
					}

				}
				freeHtml += "</div></div></div>"
			}
			// Display chugim with space.  Link to the reporting page.
			var loc = window.location;
			var basePath = removeLastDirectoryPartOf(loc.pathname);
			var edahQueryString = "";
			$.each(edahId2Name, function (edahId, edahName) {
				edahQueryString += "&edah_ids%5B%5D=" + edahId;
			});
			var groupQueryString = "";
			$.each(groupId2Name, function (groupId, groupName) {
				groupQueryString += "&group_ids%5B%5D=" + groupId;
			});
			
			var reportLink = "<a class=\"btn btn-dark text-light mt-2\" role=\"button\" href=\"" + loc.protocol + "//" + loc.hostname + ":" + loc.port + basePath + "/report.php?report_method=7&do_report=1&block_ids%5B%5D=" + block + edahQueryString + groupQueryString + "&submit=Display\">Free Space Report</a>";
			freeHtml += reportLink + "</div></div></div></div>";
			$("#results").html(freeHtml);
			$("#results:visible").removeAttr("style").fadeOut();
			$("#results").show("slide", 500);
			$("#results").attr('disabled', false);
		},
		error: function (xhr, desc, err) {
			console.log(xhr);
			console.log("Details: " + desc + "\nError:" + err);
		}});
}

// Helper function to display a Bootstap "toast" message - alert popup in lower-right corner
// Used for autosave functionality
function showToast(type, title, message) {
    const toastId = `toast-${Date.now()}`;
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center bg-${type}-subtle border-0 m-3" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>${message}
                </div>
                <button type="button" class="btn-close btn-close-dark me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>`;
    
    // Append the toast to the container
    const toastContainer = document.getElementById("toast-container");
    toastContainer.insertAdjacentHTML("beforeend", toastHTML);

    // Initialize and show the toast
    const toastElement = document.getElementById(toastId);
    const bootstrapToast = new bootstrap.Toast(toastElement);
    bootstrapToast.show();

    // Remove the toast after it hides
    toastElement.addEventListener("hidden.bs.toast", () => {
        toastElement.remove();
    });
}

// Get the current match and chug info for this edah/block, and display it by group.
// Also, display chugim with no matches, because the user needs the ability to drag
// to them.
function getAndDisplayCurrentMatches() {
	var curUrl = window.location.href;
	var curUrlBase = curUrl.substr(0, curUrl.lastIndexOf("/"));
	var editChugBase = curUrlBase + "/editChug.php?eid=";
	var edah_ids = getParameterByName("edah_ids");
	var group_ids = getParameterByName("group_ids");
	var block = getParameterByName("block");
	var succeeded = false;
	var camperId2Group2PrefList;
	var chugId2Beta = {};
	var chugId2FreeSpace = {};
	var existingMatches = {};
	var deDupMatrix = {};
	var groupId2ChugId2MatchedCampers = {};
	var camperId2Edah = {};
	var prefClasses = ["li_first_choice", "li_second_choice", "li_third_choice", "li_fourth_choice"];
	$.ajax({
		url: 'levelingAjax.php',
		type: 'post',
		async: false,
		data: {
			matches_and_prefs: 1,
			edah_ids: edah_ids,
			group_ids: group_ids,
			block_id: block
		},
		success: function (json) {
			succeeded = true;
			// Display a field for each chug.  The chug fields should be grouped
			// by group (aleph, bet, gimel).  Each field should contain camper containers,
			// according to how campers are currently matched.
			// Camper containers should be labeled with the camper's name and edah, and colored according
			// to the pref level of the assignment.  They should be draggable between chug fields,
			// but only within the enclosing group (i.e., when changing the aleph assignment for
			// a camper, it should be possible to move within aleph choices only).  Also, the tooltip
			// for the camper boxes should show an ordered list of chugim, top to bottom.
			// "This, I know from nothing!" - N. Lobachevsky.
			var html = "";
			var edahNames = json["edahNames"];
			var blockName = json["blockName"];
			var groupId2Name = json["groupId2Name"];
			var groupIdSorted = json["groupIdSorted"];
			var edahId2Name = json["edahId2Name"];
			var showEdahForCamper = 0;
			if (Object.keys(edahId2Name).length > 1) {
				// Only show the edah in camper bubbles if we are leveling > 1 edah.
				showEdahForCamper = 1;
			}
			groupId2ChugId2MatchedCampers = json["groupId2ChugId2MatchedCampers"];
			groupId2EdahId2AllowedChugim = json["groupId2EdahId2AllowedChugim"];
			camperId2Group2PrefList = json["camperId2Group2PrefList"];
			existingMatches = json["existingMatches"];
			deDupMatrix = json["deDupMatrix"];
			chugId2Beta = json["chugId2Beta"];
			var camperId2Name = json["camperId2Name"];
			camperId2Edah = json["camperId2Edah"];
			var sortedGroupIds = sortedGroupIdKeys(groupId2ChugId2MatchedCampers, groupIdSorted);
			for (var j = 0; j < sortedGroupIds.length; j++) {
				var groupId = sortedGroupIds[j];
				var chugId2MatchedCampers = groupId2ChugId2MatchedCampers[groupId];
				// Add a holder for each group (aleph, bet, gimel).
				var groupName = groupId2Name[groupId];
				html += "<div class=\"groupholder card card-body bg-light mb-4\" name=\"" + groupId + "\" >\n";
				if (showEdahForCamper) {
					html += "<h3>" + groupName + " assignments</h3>\n";
				} else {
					html += "<h3>" + groupName + " assignments for " + edahNames +
						", " + blockName + "</h3>\n";
				}
				// Within each group, add a holder for campers, and then populate with
				// campers.  List chugim in alphabetical order.
				var sortedChugIds = chugIdsSortedByName(chugId2Beta, chugId2MatchedCampers);
				var notAssignedHtml = "";
				for (var i = 0; i < sortedChugIds.length; i++) {
					var chugId = sortedChugIds[i];
					var matchedCampers = chugId2MatchedCampers[chugId];
					var curCount = matchedCampers.length;
					// Add a chug holder, and put camper holders inside it.
					var chugName = chugId2Beta[chugId]["name"];
					var chugMin = chugId2Beta[chugId]["min_size"];
					var chugMax = chugId2Beta[chugId]["max_size"];
					var allowedEdahIds = chugId2Beta[chugId]["allowed_edot"]; // array
					var editChugUrl = editChugBase + chugId;
					if (chugMax == "0" ||
						chugMax == 0 ||
						chugMax == "10000" ||
						chugMax == 10000 ||
						chugMax === null ||
						(typeof (chugMax) === 'undefined')) {
						chugMax = "no limit";
						chugId2FreeSpace[chugId] = "unlimited";
					}
					if (chugMin == "-1" ||
						chugMin == -1 ||
						chugMin === null ||
						(typeof (chugMin) === 'undefined')) {
						chugMin = "no minimum";
					} else if ((!(chugId in chugId2FreeSpace)) &&
						chugMax > curCount) {
						chugId2FreeSpace[chugId] = chugMax - curCount;
					}
					var colorClass = getColorForCount(curCount, chugMin, chugMax);
					var useGroupId = "";
					if (chugId == -1) {
						useGroupId = "_" + groupId; 
					}
					htmlChug = "<div id=\"chugholder_" + chugId + useGroupId + "\" name=\"" + chugId + "\" class=\"ui-widget ui-helper-clearfix chugholder card-body bg-white border rounded mb-3 pb-0 ui-droppable\">\n";
					if (chugName == "Not Assigned Yet") {
						htmlChug += "<h4><font color=\"red\">" + chugName + "</font></h4>";
					} else {
						htmlChug += "<h4>" + "<a href=\"" + editChugUrl + "\">" + chugName + "</a>"
							+ " (min = " + chugMin + ", max = " + chugMax + ", <span name=\"curCountHolder\" class=\"" + colorClass + "\" value=\"" + curCount + "\">cur = " + curCount + "</span>)</h4>";
					}
					htmlChug += "<ul class=\"gallery ui-helper-reset ui-helper-clearfix\">";
					htmlChug += "<div class=\"row row-cols-1 row-cols-md-2 justify-content-center mt-2\">"
					$.each(matchedCampers,
						function (index, camperId) {
							var camperName = camperId2Name[camperId];
							var edahId = camperId2Edah[camperId];
							var camperEdah = edahId2Name[edahId];
							var camperEdahText = "";
							if (Object.keys(edahId2Name).length > 1) {
								camperEdahText = "<p class=\"card-body ps-1 pe-1 mb-0 d-flex align-items-center m-0\" style=\"font-size:70%\">(" + camperEdah + ")</p>";
							}
							var prefListText = "";
							var prefClass = prefClasses[prefClasses.length - 1];
							if (camperId in camperId2Group2PrefList) {
								var group2PrefList = camperId2Group2PrefList[camperId];
								if (groupId in group2PrefList) {
									var prefList = group2PrefList[groupId];
									$.each(prefList, function (index, prefChugId) {
										if (prefListText == "") {
											prefListText += "Preferences:<ol>";
										}
										if (prefChugId in chugId2Beta) {
											prefListText += "<li>" + chugId2Beta[prefChugId]["name"] + "</li>";
										}
										if (prefChugId == chugId) {
											if (index < prefClasses.length) {
												prefClass = prefClasses[index];
											} else {
												prefClass = prefClasses[prefClasses.length - 1];
											}
										}
									});
									prefListText += "</ol>"
								}
								else {
									prefClass = "li_no_pref";
								}
							}
							else {
								prefClass = "li_no_pref";
							}
							var titleText = "title=\"No preferences\"";
							if (prefListText) {
								// If we have a pref list, write it as a tool tip.
								titleText = "title=\"" + prefListText + "\"";
							}
							htmlChug += "<li value=\"" + camperId + "\" data-bs-toggle=\"tooltip\" data-bs-html=\"true\" data-bs-placement=\"bottom\" class=\" " + prefClass + " card p-0\" " + titleText;
							htmlChug += "><h5 class=\"card-header text-break p-1 mb-0 d-flex align-items-center justify-content-center h-100\">" + camperName + "</h5>"+ camperEdahText + "<div class=\"dup-warning\"></div></li>\n";
						});
					htmlChug += "</div></ul><br style=\"clear: both\"></div>\n";
					if (chugId != -1) {
						html += htmlChug;
					}
					else {
						notAssignedHtml = htmlChug;
					}
				}
				html += notAssignedHtml;
				html += "</div>\n";
			};

			// Display chugim with space
			displayChugimWithSpace(edah_ids, group_ids, block);
			
			// Display matches and chugim.
			$("#fillmatches").html(html);

			// enable tooltips
			var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
			var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
				return new bootstrap.Tooltip(tooltipTriggerEl)
			})

			// Variables for context menu (right-click on camper tiles)
			const contextMenu = $("#context-menu");
			const contextMenuOptions = $("#context-menu-options");
			var targetElement = null; // target for when element is right-clicked on
		
			// Right-click event on camper tiles
			$(document).on("contextmenu", ".gallery li", function (event) {
				event.preventDefault();
		
				// Get variables
				camperId = $(this).attr("value");
				groupId = $(this).closest(".groupholder").attr("name");
				edahId = camperId2Edah[camperId];
				curChugId = $(this).closest(".chugholder").attr("name");
		
				contextMenuOptions.empty(); // Clear previous options
		
				// Fetch available chugim for this group
				let availableChugim = groupId2EdahId2AllowedChugim[groupId][edahId];
		
				// Set available chugim
				availableChugim.forEach(function (chugId) {
					if (chugId == curChugId) {
						return;
					}
					let chugName = chugId2Beta[chugId]["name"];
					let menuItem = $("<li>").text(chugName).attr("data-chug-id", chugId).attr("camper-id", camperId);
					contextMenuOptions.append(menuItem);
				});

				// Unassign option (and make it red!)
				let chugName = "Unassign Camper";
				let menuItem = $("<li style=\"color: red\">").text(chugName).attr("data-chug-id", -1).attr("camper-id", camperId);
				contextMenuOptions.append(menuItem);

				// Save the target element (so we can remove it from the list it is currently in and 
				// add it to the new one - change chug assignment)
				targetElement = event.target.parentElement;
				if (targetElement.nodeName == "DIV") {
					// If the specific element being clicked on was a DIV (as opposed to H5 or P), we know it was the dup-warning for a camper
					// Instead, we want to move up a level to maintain consistency with the H5 and P elements which could be clicked on
					// Hierarchy:
					/* 	<li>
					 *		<h5>
					 *		<p>
					 *		<div DUP> - usually empty, holds dup warning if present
					 *			<div CARD> - written when there is a dup warning - able to be clicked on
					 */
					targetElement = targetElement.parentElement;
				}

				// ChugId it comes from
				var sourceChugId = $(targetElement).closest(".chugholder").attr("name");
				$(this).data("sourceChugId", sourceChugId);
		
				// Position menu
				contextMenu.css({ top: event.pageY + "px", left: event.pageX + "px" }).show();
			});
		
			// Handle chug selection
			$(document).on("click", "#context-menu-options li", function () {
				// Uses very similar code from when elements are dropped, except some data (e.g. ids, elements)
				// is from different sources. There is just enough different (plus the challenge of either simulating
				// a dropped element, or creating and calling a function with 8 parameters) it felt better to 
				// just re-use the code here
				let newChugId = $(this).attr("data-chug-id");
				let camperId = $(this).attr("camper-id");
				var groupId = $(targetElement).parent().parent().parent().parent().attr("name");

				if (newChugId == -1) {
					newChugId = "-1_"+groupId
				}
				var droppedOn = document.getElementById("chugholder_"+newChugId).getElementsByClassName('row')[0];
				var droppedChugId = newChugId;
				var dropped = targetElement;
				// Change the color of the dropped item according to the camper's
				// preference for the dropped-on chug.
				var chugId2MatchedCampers = groupId2ChugId2MatchedCampers[groupId];
				var prefClass = prefClasses[prefClasses.length - 1];
				if (camperId in camperId2Group2PrefList) {
					var group2PrefList = camperId2Group2PrefList[camperId];
					if (groupId in group2PrefList) {
						var prefList = group2PrefList[groupId];
						$.each(prefList, function (index, prefChugId) {
							if (prefChugId == droppedChugId) {
								var idx = (index < prefClasses.length) ? index : prefClasses.length - 1;
								prefClass = prefClasses[idx];
								return false; // break
							}
						});
					}
					else {
						prefClass = "li_no_pref";
					}
				}
				else {
					prefClass = "li_no_pref";
				}
				if (prefClass) {
					$.each(prefClasses, function (index, prefClassToRemove) {
						// Remove old color class.
						$(dropped).removeClass(prefClassToRemove);
					});
					// Add new color class.
					$(dropped).addClass(prefClass);
				}
				$(dropped).detach().css({ top: 0, left: 0 }).appendTo(droppedOn);
				// Check to see if the dropped-on chug is a duplicate for the dropped
				// camper, and if so, show a warning.
				var dupWarningDiv = $(dropped).find(".dup-warning");
				$(dupWarningDiv).hide(); // Hide dup warning by default.
				// Store sourceChugId in a variable, and remove it from the element.
				var sourceChugId = $(dropped).data("sourceChugId");
				$(dropped).removeData("sourceChugId");
				if (camperId in existingMatches) {
					var matchHash = existingMatches[camperId];
					// Update matchHash: we need to remove the chug from which the camper
					// was dragged, and add the one in which they were dropped.  We won't
					// count the one in which they were dropped when we check for dups.
					delete matchHash[sourceChugId];
					var ourBlockName = $(".blockfill").text().substring(12);
					matchHash[droppedChugId] = ourBlockName;
					var dupId = isDupOf(droppedChugId, matchHash,
						deDupMatrix, chugId2MatchedCampers,
						$(dropped).attr('value'));
					if (dupId in chugId2Beta && dupId != -1) {
						var dupName = chugId2Beta[dupId]["name"];
						var dupGroup = chugId2Beta[dupId]["group_name"];
						var dupBlockName = matchHash[dupId];
						$(dupWarningDiv).html("<div class=\"card bg-warning border-danger border-3 m-2 mt-0\" style=\"font-size: 70%\">Dup of " + dupName + " (" + dupBlockName + " " + dupGroup + ")</div>");
						$(dupWarningDiv).fadeIn("fast");
					}
				}
				// Update counts.
				updateCount(chugId2Beta, droppedOn);
				autosave();
		
				// Reset
				targetElement = null;
				contextMenu.hide();
			});
		
			// Hide context menu when clicking elsewhere
			$(document).click(function () {
				targetElement = null;
				contextMenu.hide();
			});
		},
		error: function (xhr, desc, err) {
			console.log(xhr);
			console.log("Details: " + desc + "\nError:" + err);
		}
	}).then(function () {
		if (succeeded) {
			$("ul.gallery li").draggable({
				scroll: true,
				revert: "invalid", // when not dropped, the item will revert back
				cursor: "move"
			});
			$('ul.gallery li').each(function () {
				var $el = $(this);
				$el.draggable({
					containment: $el.closest('.groupholder'),
					start: function (event, ui) {
						// Store the ID of the chug from which we're being dragged.
						var sourceChugId = $(this).closest(".chugholder").attr("name");
						$(this).data("sourceChugId", sourceChugId);
					}
				});
			});
			// Let chug holders be droppable.  When a camper holder is dragged, move from
			// old chug to new, and update the preference color.
			$('.chugholder').each(function () {
				var $el = $(this);
				var groupId = $el.parent().attr('name');
				var chugId2MatchedCampers = groupId2ChugId2MatchedCampers[groupId];
				$el.droppable({
					accept: function (dropped) {
						// Only accept a camper bubble in this chug if the camper's edah
						// is allowed for that chug.
						var camperId = $(dropped).attr("value");
						var camperEdahId = camperId2Edah[camperId];
						var droppedOn = $(this).find(".gallery").addBack(".gallery");
						var droppedChugId = $(droppedOn).parent().attr("name");
						if (!droppedChugId in chugId2Beta) {
							return true; // Default to true.
						}
						var allowedEdotForChug = chugId2Beta[droppedChugId]["allowed_edot"]; // array
						if (allowedEdotForChug == undefined) {
							return true; // This will be the case for "Not Yet Assigned".
						}
						for (var i = 0; i < allowedEdotForChug.length; i++) {
							if (allowedEdotForChug[i] == camperEdahId) {
								return true; // This edah is allowed for this chug.
							}
						}
						return false;
					},
					//accept: "ul.gallery li",
					activeClass: "ui-state-active",
					hoverClass: "ui-state-hover",
					drop: function (event, ui) {
						var droppedOn = $(this).find(".row").addBack(".row");
						var droppedChugId = $(droppedOn).parent().parent().attr("name");
						var allowedEdotForChug = chugId2Beta[droppedChugId]["allowed_edot"]; // array
						var dropped = ui.draggable;
						// Change the color of the dropped item according to the camper's
						// preference for the dropped-on chug.
						var camperId = $(dropped).attr("value");
						var camperEdahId = camperId2Edah[camperId];
						var groupId = $(this).parent().attr("name");
						var prefClass = prefClasses[prefClasses.length - 1];
						if (camperId in camperId2Group2PrefList) {
							var group2PrefList = camperId2Group2PrefList[camperId];
							if (groupId in group2PrefList) {
								var prefList = group2PrefList[groupId];
								$.each(prefList, function (index, prefChugId) {
									if (prefChugId == droppedChugId) {
										var idx = (index < prefClasses.length) ? index : prefClasses.length - 1;
										prefClass = prefClasses[idx];
										return false; // break
									}
								});
							}
							else {
								prefClass = "li_no_pref";
							}
						}
						else {
							prefClass = "li_no_pref";
						}
						if (prefClass) {
							$.each(prefClasses, function (index, prefClassToRemove) {
								// Remove old color class.
								$(dropped).removeClass(prefClassToRemove);
							});
							// Add new color class.
							$(dropped).addClass(prefClass);
						}
						$(dropped).detach().css({ top: 0, left: 0 }).appendTo(droppedOn);
						// Check to see if the dropped-on chug is a duplicate for the dropped
						// camper, and if so, show a warning.
						var dupWarningDiv = $(dropped).find(".dup-warning");
						$(dupWarningDiv).hide(); // Hide dup warning by default.
						// Store sourceChugId in a variable, and remove it from the element.
						var sourceChugId = $(dropped).data("sourceChugId");
						$(dropped).removeData("sourceChugId");
						if (camperId in existingMatches) {
							var matchHash = existingMatches[camperId];
							// Update matchHash: we need to remove the chug from which the camper
							// was dragged, and add the one in which they were dropped.  We won't
							// count the one in which they were dropped when we check for dups.
							delete matchHash[sourceChugId];
							var ourBlockName = $(".blockfill").text().substring(12);
							matchHash[droppedChugId] = ourBlockName;
							var dupId = isDupOf(droppedChugId, matchHash,
								deDupMatrix, chugId2MatchedCampers,
								$(dropped).attr('value'));
							if (dupId in chugId2Beta && dupId != -1) {
								var dupName = chugId2Beta[dupId]["name"];
								var dupGroup = chugId2Beta[dupId]["group_name"];
								var dupBlockName = matchHash[dupId];
								$(dupWarningDiv).html("<div class=\"card bg-warning border-danger border-3 m-2 mt-0\" style=\"font-size: 70%\">Dup of " + dupName + " (" + dupBlockName + " " + dupGroup + ")</div>");
								$(dupWarningDiv).fadeIn("fast");
							}
						}
						// Update counts.
						updateCount(chugId2Beta, droppedOn);
						autosave();
					}
				});
			});
		} // End if succeeded
	});
}

// Get the name for the current edah and block IDs, and fill them.
$(function () {
	var edahIds = getParameterByName('edah_ids');
	var blockId = getParameterByName('block');
	$.ajax({
		url: 'levelingAjax.php',
		type: 'post',
		async: false,
		data: {
			names_for_id: 1,
			edah_ids: edahIds,
			block_id: blockId
		},
		success: function (json) {
			$(".edahfill").text(function () {
				if (json.edahNames &&
					json.edahNames.length > 0) {
					return $(this).text().replace("EDAH", json.edahNames);
				}
			});
			$(".chugimfill").text(function () {
				if (json.chugimTerm &&
					json.chugimTerm.length > 0) {
					return $(this).text().replace("CHUGIM", json.chugimTerm);
				}
			});
			$(".chugfill").text(function () {
				if (json.chugTerm &&
					json.chugTerm.length > 0) {
					return $(this).text().replace("CHUG", json.chugTerm);
				}
			});
			$(".blockfill").text(function () {
				if (json.blockName &&
					json.blockName.length > 0) {
					return $(this).text().replace("BLOCK", json.blockName);
				}
			});
			$(".blocktermfill").text(function () {
				console.log("DBG: block term " + json.blockTerm);
				if (json.blockTerm &&
					json.blockTerm.length > 0) {
					console.log("DBG: replacing block term");
					return $(this).text().replace("BLOCK_TERM", json.blockTerm);
				}
			});
			$(".edahtermbothfill").text(function () {
				if (json.edahBothTerm &&
					json.edahBothTerm.length > 0) {
					return $(this).text().replace("EDAH_TERM_COMBO", json.edahBothTerm);
				}
			});
			$(".edahtermfill").text(function () {
				if (json.edahTerm &&
					json.edahTerm.length > 0) {
					return $(this).text().replace("EDAH_TERM", json.edahTerm);
				}
			});
		},
		error: function (xhr, desc, err) {
			console.log(xhr);
			console.log("Details: " + desc + "\nError:" + err);
		}
	})
});

// Action for the Report button.
$(function () {
	$("#Report").click(function (event) {
		event.preventDefault();
		// Simulate clicking a link, so this page goes in the browser history.
		var baseUrl = window.location.href;
		baseUrl = baseUrl.replace("levelHome.html", "report.php");
		var edahText = "";
		var edah_ids = getParameterByName("edah_ids");
		for (var i = 0; i < edah_ids.length; i++) {
			edahText += "&edah_ids%5B%5D=" + edah_ids[i];
		}
		var block = getParameterByName("block");
		var reportUrl = baseUrl.split("?")[0];
		var reportUrl = reportUrl + "?report_method=1&block_ids%5B%5D=" + block + edahText + "&do_report=1&submit=Display";
		window.location.href = reportUrl;
	})
});

// Action for the Cancel button.
$(function () {
	$("#Cancel").click(function (event) {
		event.preventDefault();
		// Simulate clicking a link, so this page goes in the browser history.
		var curUrl = window.location.href;
		var homeUrl = curUrl.replace("levelHome.html", "staffHome.php");
		// Remove query string before redir.
		var qpos = homeUrl.indexOf("?");
		if (qpos > 0) {
			homeUrl = homeUrl.substr(0, qpos);
		}
		window.location.href = homeUrl;
	})
});

// Action for the Reassign button.
$(function () {
	var edah_ids = getParameterByName("edah_ids");
	var group_ids = getParameterByName("group_ids");
	var block = getParameterByName("block");
	$("#Reassign").click(function (event) {
		event.preventDefault();
		var r = confirm("Reassign campers on page? Please click OK to confirm.");
		if (r != true) {
			return;
		}
		var ajaxAction = function () {
			var ra = $.Deferred();
			doAssignmentAjax("reassign", "Assignment saved!", "reassign",
				edah_ids, group_ids, block);
			ra.resolve();
			return ra;
		};
		var displayAction = function () {
			getAndDisplayCurrentMatches();
		};
		ajaxAction().then(displayAction);
	});
});

// Action for the Save button
// Collect the current assignments and send them to the ajax page to be
// saved in the DB.
$(function () {
	var edah_ids = getParameterByName("edah_ids");
	var group_ids = getParameterByName("group_ids");
	var block = getParameterByName("block");
	$("#Save").click(function (event) {
		event.preventDefault();
		var r = confirm("Save changes? Please click OK to confirm.");
		if (r != true) {
			return;
		}
		save(edah_ids, group_ids, block);
	});
});


// Prepare autosaver
let saveTimer;
function autosave() {
    clearTimeout(saveTimer); // Clear any pending save calls
    saveTimer = setTimeout(() => {
		const edah_ids = getParameterByName("edah_ids");
		const group_ids = getParameterByName("group_ids");
		const block = getParameterByName("block");
        save(edah_ids, group_ids, block);
    }, 3000); // Adjust the delay (in milliseconds) as needed
}

// Save function
async function save(edah_ids, group_ids, block) {
	showToast("info", "Loading", "Please wait, save in progress...");
	await new Promise(resolve => setTimeout(resolve, 100));
	// Loop through the groups, and then loop through the
	// chugim within each group.
	// + adds short delay so browser renders "loading" message
	var assignments = new Object(); // Associative array
	var groupDivs = $(document).find(".groupholder");
	for (var i = 0; i < groupDivs.length; i++) {
		var groupElement = groupDivs[i];
		var groupId = groupElement.getAttribute("name");
		var chugDivs = $(groupElement).find(".chugholder");
		assignments[groupId] = new Object();// Associative array
		for (var j = 0; j < chugDivs.length; j++) {
			var chugDiv = chugDivs[j];
			var chugId = chugDiv.getAttribute("name");
			var ulElement = $(chugDiv).find("ul");
			var camperElements = $(ulElement).find("li");
			assignments[groupId][chugId] = [];
			for (var k = 0; k < camperElements.length; k++) {
				var camperElement = camperElements[k];
				var camperId = camperElement.getAttribute("value");
				assignments[groupId][chugId].push(camperId);
			}
		}
	}
	var values = {};
	values["save_changes"] = 1;
	values["assignments"] = assignments;
	values["edah_ids"] = edah_ids;
	values["group_ids"] = group_ids;
	values["block"] = block;
	try {
		const response = await $.ajax({
			url: 'levelingAjax.php',
			type: 'post',
			data: values
		});
		doAssignmentAjax("get_current_stats", "Changes Saved! Stats:", "save your changes",
			edah_ids, group_ids, block);
		displayChugimWithSpace(edah_ids, group_ids, block);
		showToast("success", "Success", "Updated " + response.chugimTerm + " were successfully saved.");
	} catch (error) {
		console.log(error);
		console.log("Details: " + error.statusText + "\nError: " + error.responseJSON.error);
		showToast("danger", "Error", "Something went wrong while saving your changes. Please try again.");
	}
}