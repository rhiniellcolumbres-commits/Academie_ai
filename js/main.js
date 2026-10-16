
document.addEventListener('DOMContentLoaded', function () {

    const assessmentForm = document.getElementById('assessmentForm');

    if (!assessmentForm) {
        return;
    }

    // Select only visible career-selection radio buttons.
    const careerChoices = assessmentForm.querySelectorAll(
        'input[name="career_goal"][type="radio"]'
    );

    careerChoices.forEach(function (choice) {

        choice.addEventListener('change', function () {

            careerChoices.forEach(function (item) {
                const card = item.closest('.career-card');

                if (card) {
                    card.classList.remove('selected');
                }
            });

            const selectedCard = this.closest('.career-card');

            if (selectedCard) {
                selectedCard.classList.add('selected');
            }

        });

    });

    // Validate career selection only when career radio buttons exist.
    if (careerChoices.length > 0) {

        assessmentForm.addEventListener('submit', function (event) {

            const selectedCareer = assessmentForm.querySelector(
                'input[name="career_goal"][type="radio"]:checked'
            );

            if (!selectedCareer) {
                event.preventDefault();
                alert('Please select a tech career first.');
            }

        });

    }

});