<div class="container-fluid position-absolute w-100 h-100">
    <div class="row h-75 m-0 justify-content-center align-items-center">
        <div class="col-xl-4 col-lg-6 col-sm-8 col-12 bg-white">
            <div class="col-inner text-center">
                [[+aaz.error:notempty=`
                <div class="alert alert-danger mb-5 d-flex align-items-center text-left" role="alert">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                    <div class="ml-3">
                        <p>[[+aaz.error]]</p>
                        <span>Error ID: [[+aaz.error_id]]</span>
                    </div>
                </div>
                `]]
                <div class="border-bottom w-100 d-block text-center pb-2">
                    <h5>Welcome to [[++site_name]]</h5>
                </div>
                <div class="mt-4">
                    <a href="[[+aaz.login_url]]">
                        <button class="btn btn-lg btn-block btn-primary d-flex justify-content-center align-items-center" title="Login with Microsoft Account" type="button" style="white-space: normal;">
                            <i class="fab fa-windows fa-2x"></i>
                            <span class="ml-3">Login with Microsoft Account</span>
                        </button>
                    </a>
                </div>
                <div class="mt-3">
                    <span class="small text-muted">For assistance please contact the Site Administrator</span>
                </div>
            </div>
        </div>
    </div>
</div>
