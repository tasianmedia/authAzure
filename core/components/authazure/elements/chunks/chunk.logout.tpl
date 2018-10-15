<div class="container-fluid position-absolute w-100 h-100">
    <div class="row h-75 m-0 justify-content-center align-items-center">
        <div class="col-xl-4 col-lg-6 col-sm-8 col-12 bg-white">
            <div class="col-inner text-center">
                [[+aaz.error:notempty=`
                <div class="alert alert-danger mb-5 d-flex align-items-center text-left" role="alert">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                    <span class="ml-3">[[+aaz.error]]</span>
                </div>
                `]]
                <div class="border-bottom w-100 d-block text-center pb-2">
                    <h5>Are you sure you want to logout from [[++site_name]]?</h5>
                </div>
                <div class="mt-4">
                    <a href="[[+aaz.logout_url]]">
                        <button class="btn btn-lg btn-block btn-danger d-flex justify-content-center align-items-center" title="Logout of [[++site_name]]" type="button" style="white-space: normal;">
                            <i class="fas fa-sign-out-alt fa-2x"></i>
                            <span class="ml-3">Logout</span>
                        </button>
                    </a>
                </div>
                <div class="mt-4">
                    <a href="[[+aaz.logout_azure_url]]">
                        <button class="btn btn-lg btn-block btn-primary d-flex justify-content-center align-items-center" title="Logout of [[++site_name]]" type="button" style="white-space: normal;">
                            <i class="fab fa-windows fa-2x"></i>
                            <span class="ml-3">Logout from Microsoft Account</span>
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
