// Use Vanilla JS
// Use ES6
(function() {
    // Get all .has-subsections .subsection-menu a elements
    const menuLinks = document.querySelectorAll('.has-subsections .subsection-menu a[data-target-subsection]');
    // Get all .has-subsections form .subsection elements
    const fields = document.querySelectorAll('.has-subsections form .subsection');
    // Loop through all .has-subsections .subsection-menu a elements
    for (var i = 0; i < menuLinks.length; i++) {
        // Add a click event listener to the current .has-subsections .subsection-menu a element
        menuLinks[i].addEventListener('click', function(e) {
            // Prevent the default action of the click event
            e.preventDefault();
            // Get the data-target-subsection attribute of the current .has-subsections .subsection-menu a element
            var target = this.getAttribute('data-target-subsection');
            // Loop through all .has-subsections form .subsection elements
            for (var j = 0; j < fields.length; j++) {
                // If the current .has-subsections form .subsection element has the same data-target-subsection attribute as the current .has-subsections .subsection-menu a element
                // If fields[j] has the class .active
                if (fields[j].classList.contains(target)) {
                    // Show the current .has-subsections form .subsection element
                    fields[j].style.display = 'block';
                } else {
                    // Hide the current .has-subsections form .subsection element
                    fields[j].style.display = 'none';
                }
            }
            // Loop through all .has-subsections .subsection-menu a elements
            for (var k = 0; k < menuLinks.length; k++) {
                // If this is the current .has-subsections .subsection-menu a element
                if (menuLinks[k] === this) {
                    // Add the active class to the current .has-subsections .subsection-menu a element
                    menuLinks[k].classList.add('current');
                } else {
                    // Remove the active class from the current .has-subsections .subsection-menu a element
                    menuLinks[k].classList.remove('current');
                }
            }
        });
    }
    // On page load, trigger the click event of the first .has-subsections .subsection-menu a element
    if (menuLinks.length > 0) {
        menuLinks[0].click();
    }
})();