let profile = document.querySelector('.profile');
let menu = document.querySelector('.menu');

profile.addEventListener("click", (e) => {
	menu.classList.toggle("active");
	e.stopPropagation(); // Prevents the event from bubbling to the document
});

// Prevent click inside menu from closing it
menu.addEventListener("click", (e) => {
	e.stopPropagation();
});

// Close menu when clicking outside
document.addEventListener("click", () => {
	menu.classList.remove("active");
});
