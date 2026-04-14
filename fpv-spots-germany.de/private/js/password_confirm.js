document.getElementById('passwordForm').addEventListener('submit', function(e) {
    // 1. Variablen der Eingabefelder holen
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirm').value;
    const errorDiv = document.getElementById('passwordError');

    // 2. Vergleich prüfen
    if (password !== confirm) {
        // Stoppe das Absenden des Formulars
        e.preventDefault();
        
        // Zeige Fehlermeldung an
        errorDiv.style.display = 'block';
        
        // Optisches Feedback (roter Rahmen)
        document.getElementById('password_confirm').style.borderColor = 'red';
    } else {
        // Falls sie doch übereinstimmen (bei erneutem Versuch)
        errorDiv.style.display = 'none';
        document.getElementById('password_confirm').style.borderColor = '';
    }
});