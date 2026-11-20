# Adapted from nixpkgs pkgs/by-name/fr/frankenphp/package.nix, built against ./php.nix instead of
# overriding the default PHP.
{
  buildGo127Module,
  fetchFromGitHub,
  php,
  brotli,
  watcher,
  pkg-config,
  makeBinaryWrapper,
  versionCheckHook,
}:

buildGo127Module (finalAttrs: {
  pname = "frankenphp";
  version = "1.13.6";

  src = fetchFromGitHub {
    owner = "php";
    repo = "frankenphp";
    tag = "v${finalAttrs.version}";
    hash = "sha256-8V/yfi4z8rqe+8MARDLkwGVZ+mT6DcH2KaDRJeuWzpM=";
  };

  sourceRoot = "${finalAttrs.src.name}/caddy";

  # FrankenPHP ships C code that `go mod tidy` would drop: https://github.com/golang/go/issues/26366
  proxyVendor = true;
  vendorHash = "sha256-Kzntw2a7YnkKFP9xe/mSG6v5LwisiAWKwSurSvPdbkw=";

  buildInputs = [
    php.unwrapped
    brotli
    watcher
  ]
  ++ php.unwrapped.buildInputs;
  nativeBuildInputs = [
    makeBinaryWrapper
    pkg-config
  ];

  subPackages = [ "frankenphp" ];

  tags = [
    "cgo"
    "netgo"
    "ousergo"
    "static_build"
    "nobadger"
    "nomysql"
    "nopgx"
  ];

  ldflags = [
    "-s"
    "-w"
    "-X 'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP ${finalAttrs.version} PHP ${php.version} Caddy'"
  ];

  preBuild = ''
    export CGO_CFLAGS="$(${php.unwrapped.dev}/bin/php-config --includes)"
    export CGO_LDFLAGS="-DFRANKENPHP_VERSION=${finalAttrs.version} \
      $(${php.unwrapped.dev}/bin/php-config --ldflags) \
      $(${php.unwrapped.dev}/bin/php-config --libs)"
  '';

  # The php.ini of ./php.nix is what loads its shared extensions.
  preFixup = ''
    mkdir -p $out/lib
    ln -s "${php}/lib/php.ini" "$out/lib/frankenphp.ini"

    wrapProgram $out/bin/frankenphp --set-default PHP_INI_SCAN_DIR $out/lib
  '';

  doCheck = false;

  nativeInstallCheckInputs = [ versionCheckHook ];
  versionCheckProgramArg = "version";
  doInstallCheck = true;

  meta = {
    description = "Modern PHP app server";
    homepage = "https://github.com/php/frankenphp";
    mainProgram = "frankenphp";
  };
})
