// Ambil elemen
const eye = document.getElementById("toggleBalance");
const balance = document.getElementById("balance");

// Simpan nilai asli balance saat load
const originalBalance = balance.textContent;

// Status hidden
let hidden = false;

// Event listener untuk toggle
if (eye && balance) {  // Cek jika elemen ada
    eye.addEventListener("click", () => {
        if (!hidden) {
            // Sembunyikan balance
            balance.textContent = "••••••";
            eye.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            // Tampilkan kembali balance asli
            balance.textContent = originalBalance;
            eye.classList.replace("fa-eye-slash", "fa-eye");
        }
        hidden = !hidden;
    });
} else {
    console.error("Elemen toggleBalance atau balance tidak ditemukan.");
}