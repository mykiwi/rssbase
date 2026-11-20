{
  stdenvNoCC,
  fetchurl,
  versionCheckHook,
}:

# Upstream's static binary: nixpkgs does not package Symfony Language Tools.
stdenvNoCC.mkDerivation (finalAttrs: {
  pname = "symfony-lsp";
  version = "0.24.2";

  src = fetchurl {
    url = "https://github.com/symfony/language-tools/releases/download/v${finalAttrs.version}/symfony-lsp-v${finalAttrs.version}-linux-x64.tar.gz";
    hash = "sha256-1+OUIK2QWPse3q8T8IRsXnw1FGe5BwIzbBK4zhP4oJ8=";
  };

  installPhase = ''
    runHook preInstall
    install -Dm755 symfony-lsp $out/bin/symfony-lsp
    runHook postInstall
  '';

  doInstallCheck = true;
  nativeInstallCheckInputs = [ versionCheckHook ];

  meta = {
    description = "Symfony language server and checker";
    homepage = "https://github.com/symfony/language-tools";
    mainProgram = "symfony-lsp";
    platforms = [ "x86_64-linux" ];
  };
})
