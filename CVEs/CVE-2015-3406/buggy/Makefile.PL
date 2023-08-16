use strict;
use inc::Module::Install 0.92;

$|++;

name            'Module-Signature';
license         'CC0';
all_from        'lib/Module/Signature.pm';
readme_from     'lib/Module/Signature.pm';
repository      'http://github.com/audreyt/module-signature';
install_script  'script/cpansign';
build_requires  'Test::More', 0, 'IPC::Run', 0;

# On Win32 (excluding cygwin) we know that IO::Socket::INET,
# which is needed for keyserver stuff, doesn't work. In fact
# it potentially hangs forever. So bail out with a N/A on
# Win32.
if ( $^O eq 'MSWin32' and 0 ) {
	print "Keyserver behaviour is dangerous unreliable on Win32\n";
	print "Not installing on this platform.\n";
	exit(255);
} else {
	requires 'IO::Socket::INET' => 0;
}

# We will need something to handle SHA1/256
unless (
	can_use('Digest::SHA')  or
	can_use('Digest::SHA::PurePerl') or
	(can_use('Digest::SHA1') and can_use('Digest::SHA256'))
) {
	# Nothing installed, we need to install a digest module
	if ( can_cc() ) {
		requires 'Digest::SHA';
	} else {
		requires 'Digest::SHA::PurePerl';
	}
}

# The list of OpenPGP dependencies (which we use in several places)
my @OPEN_PGP = qw{
     MIME::Base64             0
     Compress::Zlib           0
     Crypt::CBC               0
     Crypt::DES               0
     Crypt::Blowfish          0
     Crypt::RIPEMD160         0
     Tie::EncryptedHash       0
     Class::Loader            0
     Convert::ASCII::Armour   0
     Data::Buffer             0.04
     Digest::MD2              0
     Math::Pari               0
     Crypt::Random            0
     Crypt::Primes            0
     Crypt::DES_EDE3          0
     Crypt::DSA               0
     Crypt::RSA               0
     Convert::ASN1            0
     Convert::PEM             0
     Crypt::OpenPGP           1.00
};

# Is openpgp currently installed
if ( can_use('Crypt::OpenPGP') ) {
	# If OpenPGP is already installed, so relist all the
	# dependencies so they will upgrade as needed.
	requires( @OPEN_PGP );

} elsif ( my $gpg = locate_gpg() ) {
	# We SHOULD have gpg, double-check formally
	requires_external_bin $gpg;
} elsif ( can_cc() and $ENV{AUTOMATED_TESTING} ) {
	# Dive headlong into a full Crypt::OpenPGP install.
	requires( @OPEN_PGP );
} else {
	# Ask the user what to do
	ask_user();
}

unless ( can_run('diff') ) {
	# We know Text::Diff fails on Cygwin (for now)
	if ( $^O ne 'Cygwin' ) {
		requires 'Algorithm::Diff';
		requires 'Text::Diff';
	}
}

sign; WriteAll;





#####################################################################
# Support Functions

sub locate_gpg {
	print "Looking for GNU Privacy Guard (gpg), a cryptographic signature tool...\n";

  	my ($gpg, $gpg_path);
  	for my $gpg_bin ('gpg', 'gpg2', 'gnupg', 'gnupg2') {
  		$gpg_path = can_run($gpg_bin);
  		next unless $gpg_path;
  		next unless `$gpg_bin --version` =~ /GnuPG/;
  		next unless defined `$gpg_bin --list-public-keys`;
  
  		$gpg = $gpg_bin;
  		last;
  	}
  	unless ( $gpg ) {
		print "gpg not found.\n";
		return;
	}

	print "GnuPG found ($gpg_path).\n";

	return 1 if grep { /^--installdeps/} @ARGV;

	if ( prompt("Import PAUSE and author keys to GnuPG?", 'y' ) =~ /^y/i) {
		print 'Importing... ';
		system $gpg, '--quiet', '--import', glob('*.pub');
		print "done.\n";
	}

 	return $gpg;
}

sub ask_user {

    # Defined the prompt messages
    my $message1 = <<'END_MESSAGE';

Could not auto-detect a signature utility on your system.

What do you want me to do?

1) Let you install GnuPG manually while I'm waiting for your answer;
   it is available at http://www.gnupg.org/download/ or may be available
   from your platforms packaging system (for Open Source platforms).

END_MESSAGE

    my $message2 = <<'END_MESSAGE';

2) Automatically install Crypt::OpenPGP and the 20 modules it requires
   from CPAN, which will give the same functionality as GnuPG.

END_MESSAGE

	# Present the options
	print $message1;

	my $option3 = 2;
	if ( can_cc() ) {
		$option3 = 3;
		print $message2;
	}

	print <<"END_MESSAGE";

$option3) Forget this cryptographic signature stuff for now.

END_MESSAGE

	my $choice;
	foreach ( 1 .. 3 ) {
		$choice = prompt("Your choice:", 3) || 3;
		last if $choice =~ /^[123]$/;
		print "Sorry, I cannot understand '$choice'.\n"
	}

	if ( $choice == 1 ) {
		# They claim to have installed gpg
		requires_external_bin 'gpg';
	} elsif ( $choice == 2 and $option3 == 3 ) {
		# They want to install Crypt::OpenPGP
		requires( @OPEN_PGP );
	} else {
		# Forget about it...
		print "Module::Signature is not wanted on this host.\n";
		exit(0);
	}
}
