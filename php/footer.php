<?php
// footer.php
$current_year = date('Y');
?>
<!-- Footer section -->
<footer class="footer bg-dark py-5 py-lg-11 py-xl-12">
    <div class="container">
        <div class="row">
            <div class="col-xl-5 mb-8 mb-xl-0">
                <div class="d-flex flex-column gap-8 pe-xl-5">
                    <h2 class="mb-0 text-white">Raving together?</h2>
                    <div class="d-flex flex-column gap-2">
                        <a href="mailto:info@tickets.at.gabor.com" class="footer-link hstack gap-3 text-white fs-5 px-2 py-1 rounded-1 d-inline-flex">
                            <iconify-icon icon="lucide:arrow-up-right" class="fs-7 text-primary"></iconify-icon>
                            info@tickets.at.gabor.com
                        </a>
                        <a href="https://www.google.com/maps/place/VajdaságPUB/@46.2520612,20.1508211,55m/data=!3m1!1e3!4m6!3m5!1s0x474489001addf715:0xe05dc63bd12db7e!8m2!3d46.2521199!4d20.150867!16s%2Fg%2F11w1t9hyvm?entry=ttu&g_ep=EgoyMDI1MDgyNS4wIKXMDSoASAFQAw%3D%3D" target="_blank"
                           class="footer-link hstack gap-3 text-white fs-5 px-2 py-1 rounded-1 d-inline-flex">
                            <iconify-icon icon="lucide:map-pin" class="fs-7 text-primary"></iconify-icon>
                            Szeged, Roosevelt Tér 3.
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-xl-2 mb-8 mb-xl-0">
                <ul class="footer-menu list-unstyled mb-0 d-flex flex-column gap-2">
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="index.php">Home</a></li>
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="about-us.php">About us</a></li>
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="festivals.php">Festivals</a></li>
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="locations.php">Venues</a></li>
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="FAQ.php">FAQ</a></li>
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="col-md-4 col-xl-2 mb-8 mb-xl-0">
                <ul class="footer-menu list-unstyled mb-0 d-flex flex-column gap-2">
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="https://www.facebook.com/gabor.farkas.3591267/" target="_blank">Facebook</a></li>
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="https://www.instagram.com/gaaborrrrr_03/" target="_blank">Instagram</a></li>
                    <li><a class="footer-link fs-5 text-white px-2 py-1 rounded-1" href="https://www.tiktok.com/@gabore003" target="_blank">TikTok</a></li>
                </ul>
            </div>
            <div class="col-md-4 col-xl-3 mb-8 mb-xl-0">
                <p class="mb-0 text-white text-opacity-70 text-md-end"> Tickets at Gábor copyright <?php echo $current_year; ?></p>
            </div>
        </div>
    </div>
    <p class="mb-0 text-white text-opacity-70 text-md-center mt-10">If you're down here, you should now <a class="footer-link text-white px-1" href="sign-up.php">sign up</a></p>
</footer>