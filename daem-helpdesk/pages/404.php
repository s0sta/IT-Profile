<?php
declare(strict_types=1);

// The router reached an unknown route: render the shared styled error page.
render_error_page(404, t('e404.title'), t('e404.text'));
