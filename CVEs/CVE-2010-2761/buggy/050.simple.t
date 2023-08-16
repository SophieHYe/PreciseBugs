use Test::More tests => 318;
use Carp;
use strict;
use vars qw(%field %in);

use CGI::Simple;

my ( $q, $sv, @av );
my $tmpfile = './cgi-tmpfile.tmp';

$ENV{'AUTH_TYPE'}      = 'PGP MD5 DES rot13';
$ENV{'CONTENT_LENGTH'} = '42';
$ENV{'CONTENT_TYPE'}   = 'application/x-www-form-urlencoded';
$ENV{'COOKIE'} = 'foo=a%20phrase; bar=yes%2C%20a%20phrase&I%20say;';
$ENV{'DOCUMENT_ROOT'}     = '/vs/www/foo';
$ENV{'GATEWAY_INTERFACE'} = 'bleeding edge';
$ENV{'HTTPS'}             = 'ON';
$ENV{'HTTPS_A'}           = 'A';
$ENV{'HTTPS_B'}           = 'B';
$ENV{'HTTP_ACCEPT'}
 = 'text/html;q=1, text/plain;q=0.8, image/jpg, image/gif;q=0.42, */*;q=0.001';
$ENV{'HTTP_COOKIE'}     = '';
$ENV{'HTTP_FROM'}       = 'spammer@nowhere.com';
$ENV{'HTTP_HOST'}       = 'the.vatican.org';
$ENV{'HTTP_REFERER'}    = 'xxx.sex.com';
$ENV{'HTTP_USER_AGENT'} = 'LWP';
$ENV{'PATH_INFO'}       = '/somewhere/else';
$ENV{'PATH_TRANSLATED'} = '/usr/local/somewhere/else';
$ENV{'QUERY_STRING'} = 'name=JaPh%2C&color=red&color=green&color=blue';
$ENV{'REDIRECT_QUERY_STRING'} = '';
$ENV{'REMOTE_ADDR'}           = '127.0.0.1';
$ENV{'REMOTE_HOST'}           = 'localhost';
$ENV{'REMOTE_IDENT'}          = 'None of your damn business';
$ENV{'REMOTE_USER'}           = 'Just another Perl hacker,';
$ENV{'REQUEST_METHOD'}        = 'GET';
$ENV{'SCRIPT_NAME'}           = '/cgi-bin/foo.cgi';
$ENV{'SERVER_NAME'}           = 'nowhere.com';
$ENV{'SERVER_PORT'}           = '8080';
$ENV{'SERVER_PROTOCOL'}       = 'HTTP/1.0';
$ENV{'SERVER_SOFTWARE'}       = 'Apache - accept no substitutes';

$q = new CGI::Simple;

sub undef_globals {
  undef $CGI::Simple::USE_CGI_PM_DEFAULTS;
  undef $CGI::Simple::DISABLE_UPLOADS;
  undef $CGI::Simple::POST_MAX;
  undef $CGI::Simple::NO_UNDEF_PARAMS;
  undef $CGI::Simple::USE_PARAM_SEMICOLONS;
  undef $CGI::Simple::HEADERS_ONCE;
  undef $CGI::Simple::NPH;
  undef $CGI::Simple::DEBUG;
  undef $CGI::Simple::NO_NULL;
  undef $CGI::Simple::FATAL;
}

undef_globals();

# _initialize_globals()
$q->_initialize_globals();
is( $CGI::Simple::USE_CGI_PM_DEFAULTS, 0, '_initialize_globals(), 1' );
is( $CGI::Simple::DISABLE_UPLOADS,     1, '_initialize_globals(), 2' );
is( $CGI::Simple::POST_MAX, 102_400, '_initialize_globals(), 3' );
is( $CGI::Simple::NO_UNDEF_PARAMS,      0, '_initialize_globals(), 4' );
is( $CGI::Simple::USE_PARAM_SEMICOLONS, 0, '_initialize_globals(), 5' );
is( $CGI::Simple::HEADERS_ONCE,         0, '_initialize_globals(), 6' );
is( $CGI::Simple::NPH,                  0, '_initialize_globals(), 7' );
is( $CGI::Simple::DEBUG,                0, '_initialize_globals(), 8' );
is( $CGI::Simple::NO_NULL,              1, '_initialize_globals(), 9' );
is( $CGI::Simple::FATAL, -1, '_initialize_globals(), 10' );
undef_globals();

# _use_cgi_pm_global_settings()
$q->_use_cgi_pm_global_settings();
is( $CGI::Simple::DISABLE_UPLOADS, 0,
  '_use_cgi_pm_global_settings(), 1' );
is( $CGI::Simple::POST_MAX, -1, '_use_cgi_pm_global_settings(), 2' );
is( $CGI::Simple::NO_UNDEF_PARAMS, 0,
  '_use_cgi_pm_global_settings(), 3' );
is( $CGI::Simple::USE_PARAM_SEMICOLONS,
  1, '_use_cgi_pm_global_settings(), 4' );
is( $CGI::Simple::HEADERS_ONCE, 0, '_use_cgi_pm_global_settings(), 5' );
is( $CGI::Simple::NPH,          0, '_use_cgi_pm_global_settings(), 6' );
is( $CGI::Simple::DEBUG,        1, '_use_cgi_pm_global_settings(), 7' );
is( $CGI::Simple::NO_NULL,      0, '_use_cgi_pm_global_settings(), 8' );
is( $CGI::Simple::FATAL, -1, '_use_cgi_pm_global_settings(), 9' );

# _store_globals()
undef %{$q};

ok( !defined $q->{'.globals'}->{'DISABLE_UPLOADS'},
  '_store_globals(), 1' );
ok( !defined $q->{'.globals'}->{'POST_MAX'}, '_store_globals(), 2' );
ok( !defined $q->{'.globals'}->{'NO_UNDEF_PARAMS'},
  '_store_globals(), 3' );
ok( !defined $q->{'.globals'}->{'USE_PARAM_SEMICOLONS'},
  '_store_globals(), 4' );
ok( !defined $q->{'.globals'}->{'HEADERS_ONCE'},
  '_store_globals(), 5' );
ok( !defined $q->{'.globals'}->{'NPH'},     '_store_globals(), 6' );
ok( !defined $q->{'.globals'}->{'DEBUG'},   '_store_globals(), 7' );
ok( !defined $q->{'.globals'}->{'NO_NULL'}, '_store_globals(), 8' );
ok( !defined $q->{'.globals'}->{'FATAL'},   '_store_globals(), 9' );
ok( !defined $q->{'.globals'}->{'USE_CGI_PM_DEFAULTS'},
  '_store_globals(), 10' );
$q->_store_globals();

ok( defined $q->{'.globals'}->{'DISABLE_UPLOADS'},
  '_store_globals(), 11' );
ok( defined $q->{'.globals'}->{'POST_MAX'}, '_store_globals(), 12' );
ok( defined $q->{'.globals'}->{'NO_UNDEF_PARAMS'},
  '_store_globals(), 13' );
ok( defined $q->{'.globals'}->{'USE_PARAM_SEMICOLONS'},
  '_store_globals(), 14' );
ok( defined $q->{'.globals'}->{'HEADERS_ONCE'},
  '_store_globals(), 15' );
ok( defined $q->{'.globals'}->{'NPH'},     '_store_globals(), 16' );
ok( defined $q->{'.globals'}->{'DEBUG'},   '_store_globals(), 17' );
ok( defined $q->{'.globals'}->{'NO_NULL'}, '_store_globals(), 18' );
ok( defined $q->{'.globals'}->{'FATAL'},   '_store_globals(), 19' );
ok( defined $q->{'.globals'}->{'USE_CGI_PM_DEFAULTS'},
  '_store_globals(), 20' );

# import() - used to set paragmas
my @args
 = qw( -default -no_upload -unique_header -nph -no_debug -newstyle_url -no_undef_param );

undef_globals();

$q->import( @args );

is( $CGI::Simple::USE_CGI_PM_DEFAULTS,  1, 'import(), 1' );
is( $CGI::Simple::DISABLE_UPLOADS,      1, 'import(), 2' );
is( $CGI::Simple::NO_UNDEF_PARAMS,      1, 'import(), 3' );
is( $CGI::Simple::USE_PARAM_SEMICOLONS, 1, 'import(), 4' );
is( $CGI::Simple::HEADERS_ONCE,         1, 'import(), 5' );
is( $CGI::Simple::NPH,                  1, 'import(), 6' );
is( $CGI::Simple::DEBUG,                0, 'import(), 7' );
undef_globals();

$q->import( qw ( -upload -oldstyle_url  -debug  ) );

is( $CGI::Simple::DISABLE_UPLOADS,      0, 'import(), 8' );
is( $CGI::Simple::USE_PARAM_SEMICOLONS, 0, 'import(), 9' );
is( $CGI::Simple::DEBUG,                2, 'import(), 10' );
undef_globals();

# _reset_globals()
$q->_reset_globals();
is( $CGI::Simple::DISABLE_UPLOADS,      0,  '_reset_globals(), 1' );
is( $CGI::Simple::POST_MAX,             -1, '_reset_globals(), 2' );
is( $CGI::Simple::NO_UNDEF_PARAMS,      0,  '_reset_globals(), 3' );
is( $CGI::Simple::USE_PARAM_SEMICOLONS, 1,  '_reset_globals(), 4' );
is( $CGI::Simple::HEADERS_ONCE,         0,  '_reset_globals(), 5' );
is( $CGI::Simple::NPH,                  0,  '_reset_globals(), 6' );
is( $CGI::Simple::DEBUG,                1,  '_reset_globals(), 7' );
is( $CGI::Simple::NO_NULL,              0,  '_reset_globals(), 8' );
is( $CGI::Simple::FATAL,                -1, '_reset_globals(), 9' );
undef_globals();

$q = new CGI::Simple;

# url_decode() - scalar context, void argument
$sv = $q->url_decode();
is( $sv, undef, 'url_decode(), 1' );

# url_decode() - scalar context, valid argument
my ( $string, $enc_string );
for ( 32 .. 255 ) {
  $string .= chr;
  $enc_string .= uc sprintf "%%%02x", ord chr;
}
is( $q->url_decode( $enc_string ),
  $string, 'url_decode(\$enc_string), 1' );

# url_encode() - scalar context, void argument
$sv = $q->url_encode();
is( $sv, undef, 'url_encode(), 1' );

# url_encode() - scalar context, valid argument
$sv = $q->url_encode( $string );
$sv =~ tr/+/ /;
$sv =~ s/%([a-fA-F0-9]{2})/ pack "C", hex $1 /eg;
is( $sv, $string, 'url_encode(\$string), 1' );

# url encoding - circular test
is( $q->url_decode( $q->url_encode( $string ) ),
  $string, 'url encoding via circular test, 1' );

# new() plain constructor
$q = CGI::Simple->new;
like( $q, qr/CGI::Simple/, 'new() plain constructor, 1' );

# new('') empty constructor
$q = new CGI::Simple( '' );
like( $q, qr/CGI::Simple/, 'new() empty constructor, 1' );
$q = new CGI::Simple( {} );
like( $q, qr/CGI::Simple/, 'new() empty constructor, 2' );

# new() hash constructor
$q = new CGI::Simple( { 'foo' => '1', 'bar' => [ 2, 3, 4 ] } );
@av = $q->param;

# fix OS bug with testing
is( join( ' ', sort @av ), 'bar foo', 'new() hash constructor, 1' );
is( $q->param( 'foo' ), 1, 'new() hash constructor, 2' );
is( $q->param( 'bar' ), 2, 'new() hash constructor, 3' );
@av = $q->param( 'bar' );
is( join( '', @av ), 234, 'new() hash constructor, 4' );
$q = new CGI::Simple( 'foo=1&bar=2&bar=3&bar=4' );
open FH, ">$tmpfile", or carp "Can't create $tmpfile $!\n";
$q->save( \*FH );
close FH;

# new() query string constructor
$q  = new CGI::Simple( 'foo=5&bar=6&bar=7&bar=8' );
@av = $q->param;
is( join( ' ', @av ), 'foo bar', 'new() query string constructor, 1' );
is( $q->param( 'foo' ), 5, 'new() query string constructor, 2' );
is( $q->param( 'bar' ), 6, 'new() query string constructor, 3' );
@av = $q->param( 'bar' );
is( join( '', @av ), 678, 'new() query string constructor, 4' );
open FH, ">>$tmpfile", or carp "Can't append $tmpfile $!\n";
$q->save_parameters( \*FH );
close FH;

# new() CGI::Simple object constructor

my $q_old = new CGI::Simple( 'foo=1&bar=2&bar=3&bar=4' );
my $q_new = new CGI::Simple( $q_old );
is( $q_old->query_string, 'foo=1&bar=2&bar=3&bar=4',
  'new() CGI::Simple object constructor, 1' );
is( $q_new->query_string, 'foo=1&bar=2&bar=3&bar=4',
  'new() CGI::Simple object constructor, 2' );

# new() \@ARGV constructor

$ENV{'REQUEST_METHOD'} = '';
$CGI::Simple::DEBUG    = 1;
@ARGV                  = qw( foo=bar\=baz foo=bar\&baz );
$q                     = new CGI::Simple;

is(
  join( ' ', $q->param( 'foo' ) ),
  'bar=baz bar&baz',
  'new() \@ARGV constructor, 1'
);
$ENV{'REQUEST_METHOD'} = 'GET';

################ The Core Methods ################

$q = new CGI::Simple;

# param() - scalar and array context, void argument
$sv = $q->param();
@av = $q->param();
is( $sv, '2', 'param() void argument, 1' );
is( join( ' ', @av ), 'name color', 'param() void argument, 2' );

# param() - scalar and array context, single argument (valid)
$sv = $q->param( 'color' );
@av = $q->param( 'color' );
is( $sv, 'red', 'param(\'color\') single argument (valid), 1' );
is(
  join( ' ', @av ),
  'red green blue',
  'param(\'color\') single argument (valid), 2'
);

# param() - scalar and array context, single argument (invalid)
$sv = $q->param( 'invalid' );
@av = $q->param( 'invalid' );
is( $sv, undef, 'param(\'invalid\') single argument (invalid), 1' );
is( join( ' ', @av ),
  '', 'param(\'invalid\') single argument (invalid), 2' );

# param() - scalar and array context, -name=>'param' (valid)
$sv = $q->param( -name => 'color' );
@av = $q->param( -name => 'color' );
is( $sv, 'red', 'param( -name=>\'color\' ) get values, 1' );
is(
  join( ' ', @av ),
  'red green blue',
  'param( -name=>\'color\' ) get values, 2'
);

# param() - scalar and array context, -name=>'param' (invalid)
$sv = $q->param( -name => 'invalid' );
@av = $q->param( -name => 'invalid' );
is( $sv, undef, 'param( -name=>\'invalid\' ) get values, 1' );
is( join( ' ', @av ), '', 'param( -name=>\'invalid\' ) get values, 2' );
$CGI::Simple::NO_UNDEF_PARAMS = 0;
$q                            = new CGI::Simple( 'name=&color=' );

# param() - scalar and array context, void values void arg
$sv = $q->param();
@av = $q->param();
is( $sv, '2', 'param() void values 1, 1' );
is( join( ' ', @av ), 'name color', 'param() void values 1, 2' );

# param() - scalar and array context, void values, valid arg
$sv = $q->param( 'name' );
@av = $q->param( 'name' );
is( $sv, '', 'param(\'name\') void values 1, valid param, 1' );
is( join( ' ', @av ),
  '', 'param(\'name\') void values 1, valid param, 2' );
$q = new CGI::Simple( 'name&color' );

# param() - scalar and array context, void values void arg
$sv = $q->param();
@av = $q->param();
is( $sv, '2', 'param() void values 2, 1' );
is( join( ' ', @av ), 'name color', 'param() void values 2, 2' );

# param() - scalar and array context, void values, valid arg
$sv = $q->param( 'name' );
@av = $q->param( 'name' );
is( $sv, '', 'param(\'name\') void values 2 , valid param, 1' );
is( join( ' ', @av ),
  '', 'param(\'name\') void values 2 , valid param, 2' );
$CGI::Simple::NO_UNDEF_PARAMS = 1;
$q                            = new CGI::Simple( 'name=&color=' );

# param() - scalar and array context, void values void arg
$sv = $q->param();
@av = $q->param();
is( $sv, '0', 'param() void values 1, no undef, 1' );
is( join( ' ', @av ), '', 'param() void values 1, no undef, 2' );

# param() - scalar and array context, void values, valid arg
$sv = $q->param( 'name' );
@av = $q->param( 'name' );
is( $sv, undef,
  'param(\'name\') void values 1, valid param, no undef, 1' );
is( join( ' ', @av ),
  '', 'param(\'name\') void values 1, valid param, no undef, 2' );
$q = new CGI::Simple( 'name&color' );

# param() - scalar and array context, void values void arg
$sv = $q->param();
@av = $q->param();
is( $sv, '0', 'param() void values 2, no undef, 1' );
is( join( ' ', @av ), '', 'param() void values 2, no undef, 2' );

# param() - scalar and array context, void values, valid arg
$sv = $q->param( 'name' );
@av = $q->param( 'name' );
is( $sv, undef,
  'param(\'name\') void values 2, valid param, no undef, 1' );
is( join( ' ', @av ),
  '', 'param(\'name\') void values 2, valid param, no undef, 2' );
$CGI::Simple::NO_UNDEF_PARAMS = 0;
$q                            = new CGI::Simple;

# param() - scalar and array context, set values
$sv = $q->param( 'foo', 'some', 'new', 'values' );
@av = $q->param( 'foo', 'some', 'new', 'values' );
is( $sv, 'some',
  'param( \'foo\', \'some\', \'new\', \'values\' ) set values, 1' );
is(
  join( ' ', @av ),
  'some new values',
  'param( \'foo\', \'some\', \'new\', \'values\' ) set values, 2'
);

# param() - scalar and array context
$sv = $q->param( -name => 'foo', -value => 'bar' );
@av = $q->param( -name => 'foo', -value => 'bar' );
is( $sv, 'bar',
  'param( -name=>\'foo\', -value=>\'bar\' ) set values, 1' );
is( join( ' ', @av ),
  'bar', 'param( -name=>\'foo\', -value=>\'bar\' ) set values, 2' );

# param() - scalar and array context
$sv = $q->param( -name => 'foo', -value => [ 'bar', 'baz' ] );
@av = $q->param( -name => 'foo', -value => [ 'bar', 'baz' ] );
is( $sv, 'bar',
  'param(-name=>\'foo\',-value=>[\'bar\',\'baz\']) set values, 1' );
is( join( ' ', @av ),
  'bar baz',
  'param(-name=>\'foo\',-value=>[\'bar\',\'baz\']) set values, 2' );

# add_param() - scalar and array context, void argument
$sv = $q->add_param();
@av = $q->add_param();
is( $sv, undef, 'add_param(), 1' );
is( join( ' ', @av ), '', 'add_param(), 2' );

# add_param() - scalar and array context, existing param argument
$q->add_param( 'foo', 'new' );
@av = $q->param( 'foo' );
is( join( ' ', @av ),
  'bar baz new', 'add_param( \'foo\', \'new\' ), 1' );
$q->add_param( 'foo', [ 1, 2, 3, 4, 5 ] );
@av = $q->param( 'foo' );
is(
  join( ' ', @av ),
  'bar baz new 1 2 3 4 5',
  'add_param( \'foo\', \'new\' ), 2'
);

# add_param() - existing param argument, overwrite
$q->add_param( 'foo', 'bar', 'overwrite' );
@av = $q->param( 'foo' );
is( join( ' ', @av ),
  'bar', 'add_param(\'foo\', \'bar\', \'overwrite\' ), 1' );

# add_param() - scalar and array context, existing param argument
$q->add_param( 'new', 'new%2C' );
@av = $q->param( 'new' );
is( join( ' ', @av ), 'new%2C', 'add_param(  \'new\', \'new\'  ), 1' );
$q->add_param( 'new', [ 1, 2, 3, 4, 5 ] );
@av = $q->param( 'new' );
is(
  join( ' ', @av ),
  'new%2C 1 2 3 4 5',
  'add_param(  \'new\', \'new\'  ), 2'
);

# param_fetch() - scalar context, void argument
$sv = $q->param_fetch();
is( $sv, undef, 'param_fetch(), 1' );

# param_fetch() - scalar context, 'color' syntax
$sv = $q->param_fetch( 'color' );
is( ref $sv, 'ARRAY', 'param_fetch( \'color\' ), 1' );
is( join( ' ', @$sv ), 'red green blue',
  'param_fetch( \'color\' ), 2' );

# param_fetch() - scalar context, -name=>'color' syntax
$sv = $q->param_fetch( -name => 'color' );
is( ref $sv, 'ARRAY', 'param_fetch( -name=>\'color\' ), 1' );
is(
  join( ' ', @$sv ),
  'red green blue',
  'param_fetch( -name=>\'color\' ), 2'
);

# url_param() - scalar and array context, void argument
$sv = $q->url_param();
@av = $q->url_param();
is( $sv, '2', 'url_param() void argument, 1' );
is( join( ' ', @av ), 'name color', 'url_param() void argument, 2' );

# url_param() - scalar and array context, single argument (valid)
$sv = $q->url_param( 'color' );
@av = $q->url_param( 'color' );
is( $sv, 'red', 'url_param(\'color\') single argument (valid), 1' );
is(
  join( ' ', @av ),
  'red green blue',
  'url_param(\'color\') single argument (valid), 2'
);

# url_param() - scalar and array context, single argument (invalid)
$sv = $q->url_param( 'invalid' );
@av = $q->url_param( 'invalid' );
is( $sv, undef, 'url_param(\'invalid\') single argument (invalid), 1' );
is( join( ' ', @av ),
  '', 'url_param(\'invalid\') single argument (invalid), 2' );

# keywords() - scalar and array context, void argument
$q  = new CGI::Simple( 'here+are++++some%20keywords' );
$sv = $q->keywords;
@av = $q->keywords;
is( $sv, '4', 'keywords(), 1' );
is( join( ' ', @av ), 'here are some keywords', 'keywords(), 2' );

# keywords() - scalar and array context, array argument
$sv = $q->keywords( 'foo', 'bar', 'baz' );
@av = $q->keywords( 'foo', 'bar', 'baz' );
is( $sv, '3', 'keywords( \'foo\', \'bar\', \'baz\' ), 1' );
is( join( ' ', @av ),
  'foo bar baz', 'keywords( \'foo\', \'bar\', \'baz\' ), 2' );

# keywords() - scalar and array context, array ref argument
$q  = new CGI::Simple;
$sv = $q->keywords( [ 'foo', 'man', 'chu' ] );
@av = $q->keywords( [ 'foo', 'man', 'chu' ] );
is( $sv, '3', 'keywords( [\'foo\', \'man\', \'chu\'] ), 1' );
is( join( ' ', @av ),
  'foo man chu', 'keywords( [\'foo\', \'man\', \'chu\'] ), 2' );
$sv = $q->Vars();
is( $sv->{'color'}, "red\0green\0blue", 'Vars() - tied interface, 1' );
$sv->{'color'} = "foo\0bar\0baz";
is( join( ' ', $q->param( 'color' ) ),
  'foo bar baz', 'Vars() - tied interface, 2' );
$q = new CGI::Simple;

# Vars() - hash context, void argument
my %hv = $q->Vars();
is( $hv{'name'}, 'JaPh,', 'Vars(), 1' );

# Vars() - hash context, "|" argument
%hv = $q->Vars( ',' );
is( $hv{'color'}, 'red,green,blue', 'Vars(\',\'), 1' );

# append() - scalar and array context, void argument
$sv = $q->append();
@av = $q->append();
is( $sv, undef, 'append(), 1' );
is( join( '', @av ), '', 'append(), 2' );

# append() - scalar and array context, set values, valid param
$q->add_param( 'foo', 'bar', 'overwrite' );
$sv = $q->append( 'foo', 'some' );
@av = $q->append( 'foo', 'some-more' );
is( $sv, 'bar', 'append( \'foo\', \'some\' ) set values, 1' );
is(
  join( ' ', @av ),
  'bar some some-more',
  'append( \'foo\', \'some\' ) set values, 2'
);

# append() - scalar and array context, set values, non-existant param
$sv = $q->append( 'invalid', 'param1' );
@av = $q->append( 'invalid', 'param2' );
is( $sv, 'param1', 'append( \'invalid\', \'param\' ) set values, 1' );
is(
  join( ' ', @av ),
  'param1 param2',
  'append( \'invalid\', \'param\' ) set values, 2'
);
is(
  join( ' ', $q->param( 'invalid' ) ),
  'param1 param2',
  'append( \'invalid\', \'param\' ) set values, 3'
);

# append() - scalar and array context, set values
$sv = $q->append( 'foo', 'some', 'new',  'values' );
@av = $q->append( 'foo', 'even', 'more', 'stuff' );
is( $sv, 'bar',
  'append( \'foo\', \'some\', \'new\', \'values\' ) set values, 1' );
is(
  join( ' ', @av ),
  'bar some some-more some new values even more stuff',
  'append( \'foo\', \'some\', \'new\', \'values\' ) set values, 2'
);

# append() - scalar and array context
$sv = $q->append( -name => 'foo', -value => 'baz' );
@av = $q->append( -name => 'foo', -value => 'xyz' );
is( $sv, 'bar',
  'append( -name=>\'foo\', -value=>\'bar\' ) set values, 1' );
is(
  join( ' ', @av ),
  'bar some some-more some new values even more stuff baz xyz',
  'append( -name=>\'foo\', -value=>\'bar\' ) set values, 2'
);

# append() - scalar and array context
$sv = $q->append( -name => 'foo', -value => [ 1, 2 ] );
@av = $q->append( -name => 'foo', -value => [ 3, 4 ] );
is( $sv, 'bar',
  'append(-name=>\'foo\',-value=>[\'bar\',\'baz\']) set values, 1' );
is(
  join( ' ', @av ),
  'bar some some-more some new values even more stuff baz xyz 1 2 3 4',
  'append(-name=>\'foo\',-value=>[\'bar\',\'baz\']) set values, 2'
);

# delete() - void/valid argument
$q->delete();
is( join( ' ', $q->param ), 'name color foo invalid', 'delete(), 1' );
$q->delete( 'foo' );
is( join( ' ', $q->param ), 'name color invalid', 'delete(), 2' );

# Delete() - void/valid argument
$q->Delete();
is( join( ' ', $q->param ), 'name color invalid', 'Delete(), 1' );
$q->Delete( 'invalid' );
is( join( ' ', $q->param ), 'name color', 'Delete(), 2' );

# delete_all() - scalar and array context, void/invalid/valid argument
$q->delete_all();
is( join( '', $q->param ), '', 'delete_all(), 1' );
is( $q->globals, '11', 'delete_all(), 2' );
$ENV{'CONTENT_TYPE'} = 'NOT multipart/form-data';
$q = new CGI::Simple;

# delete_all() - scalar and array context, void/invalid/valid argument
is( join( ' ', $q->param ), 'name color', 'Delete_all(), 1' );
$q->Delete_all();
is( join( '', $q->param ), '', 'Delete_all(), 2' );
$ENV{'CONTENT_TYPE'} = 'application/x-www-form-urlencoded';

# upload() - invalid CONTENT_TYPE
$sv = $q->upload( 'foo' );
is( $sv, undef, 'upload() - invalid CONTENT_TYPE, 1' );
is(
  $q->cgi_error(),
  'Oops! File uploads only work if you specify ENCTYPE="multipart/form-data" in your <FORM> tag',
  'upload() - invalid CONTENT_TYPE, 2'
);
$ENV{'CONTENT_TYPE'} = 'multipart/form-data';

# upload() - scalar and array context, void/invalid/valid argument
$sv = $q->upload();
@av = $q->upload();
is( $sv, undef, 'upload() - no files available, 1' );
is( join( ' ', @av ), '', 'upload() - no files available, 2' );

# upload() - scalar and array context, files available, void arg
$q->{'.filehandles'}->{$_} = $_ for qw( File1 File2 File3 );
$sv                        = $q->upload();
@av                        = $q->upload();
is( $sv, 3, 'upload() - files available, 1' );
is(
  join( ' ', sort @av ),
  'File1 File2 File3',
  'upload() - files available, 2'
);
$q->{'.filehandles'} = {};

# upload() - scalar context, valid argument
open FH, $tmpfile or carp "Can't read $tmpfile $!\n";
my $data = join '', <FH>;
is( $data && 1, 1, 'upload(\'/some/path/to/myfile\') - real files, 1' );
seek FH, 0, 0;
$q->{'.filehandles'}->{'/some/path/to/myfile'} = \*FH;
my $handle = $q->upload( '/some/path/to/myfile' );
my $upload = join '', <$handle>;
is( $upload, $data,
  'upload(\'/some/path/to/myfile\') - real files, 2' );

# upload() - scalar context, invalid argument
$sv = $q->upload( 'invalid' );
is( $sv, undef, 'upload(\'invalid\'), 1' );
is(
  $q->cgi_error,
  "No filehandle for 'invalid'. Are uploads enabled (\$DISABLE_UPLOADS = 0)? Is \$POST_MAX big enough?",
  'upload(\'invalid\'), 2'
);
my $ok = $q->upload( '/some/path/to/myfile', "$tmpfile.bak" );
is( $ok, 1, 'upload(\'invalid\'), 3' );
open $handle, "$tmpfile.bak" or carp "Can't read $tmpfile.bak $!\n";
$upload = join '', <$handle>;
is( $upload, $data, 'upload(\'invalid\'), 4' );
$sv = $q->upload( '/some/path/to/myfile', "$tmpfile.bak" );
is( $sv, undef, 'upload(\'invalid\'), 5' );
unlink $tmpfile, "$tmpfile.bak";

$ENV{'CONTENT_TYPE'} = 'application/x-www-form-urlencoded';
$q = new CGI::Simple;

# query_string() - scalar and array context, void/invalid/valid argument
$sv = $q->query_string();
is(
  $sv,
  'name=JaPh%2C&color=red&color=green&color=blue',
  'query_string(), 1'
);

# parse_query_string()
$q->delete_all;
is( $q->param, 0, 'parse_query_string(), 1' );
$ENV{'REQUEST_METHOD'} = 'POST';
$q->parse_query_string();
$sv = $q->query_string();
is(
  $sv,
  'name=JaPh%2C&color=red&color=green&color=blue',
  'parse_query_string(), 2'
);
$ENV{'REQUEST_METHOD'} = 'GET';

# parse_keywordlist() - scalar and array context
$sv = $q->parse_keywordlist( 'Just+another++Perl%20hacker%2C' );
@av = $q->parse_keywordlist( 'Just+another++Perl%20hacker%2C' );
is( $sv, '4', 'parse_keywordlist(), 1' );
is(
  join( ' ', @av ),
  'Just another Perl hacker,',
  'parse_keywordlist(), 2'
);

################ Save and Restore params from file ###############

# _init_from_file()
# save() - scalar and array context, void/invalid/valid argument
# save_parameters() - scalar and array context, void/invalid/valid argument

# all tested in constructor section

################ Miscelaneous Methods ################

$q = new CGI::Simple;

# escapeHTML()
$sv = $q->escapeHTML();
is( $sv, undef, 'escapeHTML(), 1' );
$sv = $q->escapeHTML( "<>&\"\012\015<>&\"\012\015", 0 );
is(
  $sv,
  "&lt;&gt;&amp;&quot;\012\015&lt;&gt;&amp;&quot;\012\015",
  'escapeHTML(), 2'
);
$sv = $q->escapeHTML( "<>&\"\012\015<>&\"\012\015", 'newlines too' );
is(
  $sv,
  "&lt;&gt;&amp;&quot;&#10;&#13;&lt;&gt;&amp;&quot;&#10;&#13;",
  'escapeHTML(), 3'
);

# unescapeHTML()
$sv = $q->unescapeHTML();
is( $sv, undef, 'unescapeHTML(), 1' );
$sv = $q->unescapeHTML(
  "&lt;&gt;&amp;&quot;&#10;&#13;&lt;&gt;&amp;&quot;&#10;&#13;" );
is( $sv, "<>&\"\012\015<>&\"\012\015", 'unescapeHTML(), 2' );
$sv = $q->unescapeHTML(
  'Jack & Jill went up the hill; to get a pail of water' );
is(
  $sv,
  'Jack & Jill went up the hill; to get a pail of water',
  'unescapeHTML(), 3 '
);

# put()
is( $q->put( '' ), 1, 'put(), 1' );

# print()
is( $q->print( '' ), 1, 'print(), 1' );
################# Cookie Methods ################

$q = new CGI::Simple;

# raw_cookie() - scalar and array context, void argument
$sv = $q->raw_cookie();
@av = $q->raw_cookie();
is(
  $sv,
  'foo=a%20phrase; bar=yes%2C%20a%20phrase&I%20say;',
  'raw_cookie(), 1'
);
is(
  join( '', @av ),
  'foo=a%20phrase; bar=yes%2C%20a%20phrase&I%20say;',
  'raw_cookie(), 2'
);

# raw_cookie() - scalar and array context, valid argument
$sv = $q->raw_cookie( 'foo' );
@av = $q->raw_cookie( 'foo' );
is( $sv, 'a%20phrase', 'raw_cookie(\'foo\'), 1' );
is( join( '', @av ), 'a%20phrase', 'raw_cookie(\'foo\'), 2' );

# raw_cookie() - scalar and array context, invalid argument
$sv = $q->raw_cookie( 'invalid' );
@av = $q->raw_cookie( 'invalid' );
is( $sv, undef, 'raw_cookie(\'invalid\'), 1' );
is( join( '', @av ), '', 'raw_cookie(\'invalid\'), 2' );

# cookie() - scalar and array context, void argument
$sv = $q->cookie();
@av = $q->cookie();
is( $sv, '2', 'cookie(), 1' );

# fix OS perl version test bug
is( join( ' ', sort @av ), 'bar foo', 'cookie(), 2' );

# cookie() - scalar and array context, valid argument, single value
$sv = $q->cookie( 'foo' );
@av = $q->cookie( 'foo' );
is( $sv, 'a phrase', 'cookie(\'foo\'), 1' );
is( join( '', @av ), 'a phrase', 'cookie(\'foo\'), 2' );

# cookie() - scalar and array context, valid argument, multiple values
$sv = $q->cookie( 'bar' );
@av = $q->cookie( 'bar' );
is( $sv, 'yes, a phrase', 'cookie(\'foo\'), 1' );
is( join( ' ', @av ), 'yes, a phrase I say', 'cookie(\'foo\'), 2' );

# cookie() - scalar and array context, invalid argument
$sv = $q->cookie( 'invalid' );
@av = $q->cookie( 'invalid' );
is( $sv, undef, 'cookie(\'invalid\'), 1' );
is( join( '', @av ), '', 'cookie(\'invalid\'), 2' );
my @vals = (
  -name     => 'Password',
  -value    => [ 'superuser', 'god', 'open sesame', 'mydog woofie' ],
  -expires  => 'Mon, 11-Nov-2018 11:00:00 GMT',
  -domain   => '.nowhere.com',
  -path     => '/cgi-bin/database',
  -secure   => 1,
  -httponly => 1
);

# cookie() - scalar and array context, full argument set, correct order
$sv = $q->cookie( @vals );
@av = $q->cookie( @vals );
is(
  $sv,
  'Password=superuser&god&open%20sesame&mydog%20woofie; domain=.nowhere.com; path=/cgi-bin/database; expires=Mon, 11-Nov-2018 11:00:00 GMT; secure; HttpOnly',
  'cookie(\@vals) correct order, 1'
);
is(
  join( '', @av ),
  'Password=superuser&god&open%20sesame&mydog%20woofie; domain=.nowhere.com; path=/cgi-bin/database; expires=Mon, 11-Nov-2018 11:00:00 GMT; secure; HttpOnly',
  'cookie(\@vals) correct order, 2'
);

# cookie() - scalar and array context, full argument set, incorrect order
$sv
 = $q->cookie( @vals[ 0, 1, 10, 11, 12, 13, 8, 9, 2, 3, 4, 5, 6, 7 ] );
@av
 = $q->cookie( @vals[ 0, 1, 10, 11, 12, 13, 8, 9, 2, 3, 4, 5, 6, 7 ] );
is(
  $sv,
  'Password=superuser&god&open%20sesame&mydog%20woofie; domain=.nowhere.com; path=/cgi-bin/database; expires=Mon, 11-Nov-2018 11:00:00 GMT; secure; HttpOnly',
  'cookie(\@vals) incorrect order, 1'
);
is(
  join( '', @av ),
  'Password=superuser&god&open%20sesame&mydog%20woofie; domain=.nowhere.com; path=/cgi-bin/database; expires=Mon, 11-Nov-2018 11:00:00 GMT; secure; HttpOnly',
  'cookie(\@vals) incorrect order, 2'
);
my $cookie = $sv;    # save a cookie for header testing

# cookie() - scalar and array context, partial argument set
$sv = $q->cookie( -name => 'foo', -value => 'bar' );
@av = $q->cookie( -name => 'foo', -value => 'bar' );
is(
  $sv,
  'foo=bar; path=/',
  'cookie( -name=>\'foo\', -value=>\'bar\' ), 1'
);
is(
  join( '', @av ),
  'foo=bar; path=/',
  'cookie( -name=>\'foo\', -value=>\'bar\' ), 2'
);
################# Header Methods ################

$q = new CGI::Simple

 my $CRLF = $q->crlf;

# header() - scalar and array context, void argument
$sv = $q->header();
@av = $q->header();
is( $sv, "Content-Type: text/html; charset=ISO-8859-1$CRLF$CRLF",
  'header(), 1' );
is(
  join( '', @av ),
  "Content-Type: text/html; charset=ISO-8859-1$CRLF$CRLF",
  'header(), 2'
);

# header() - scalar context, single argument
$sv = $q->header( 'image/gif' );
is(
  $sv,
  "Content-Type: image/gif$CRLF$CRLF",
  'header(\'image/gif\'), 1'
);
@vals = (
  -type       => 'image/gif',
  -nph        => 1,
  -status     => '402 Payment required',
  -expires    => 'Mon, 11-Nov-2018 11:00:00 GMT',
  -cookie     => $cookie,
  -charset    => 'utf-7',
  -attachment => 'foo.gif',
  -Cost       => '$2.00'
);

# header() - scalar context, complex header
$sv = $q->header( @vals );
my $header = <<'HEADER';
HTTP/1.0 402 Payment required
Server: Apache - accept no substitutes
Status: 402 Payment required
Set-Cookie: Password=superuser&god&open%20sesame&mydog%20woofie; domain=.nowhere.com; path=/cgi-bin/database; expires=Mon, 11-Nov-2018 11:00:00 GMT; secure; HttpOnly
Expires: Mon, 11-Nov-2018 11:00:00 GMT
Date: Tue, 11-Nov-2018 11:00:00 GMT
Content-Disposition: attachment; filename="foo.gif"
Cost: $2.00
Content-Type: image/gif
HEADER
$sv     =~ s/[\012\015]//g;
$header =~ s/[\012\015]//g;
$sv     =~ s/(?:Expires|Date).*?GMT//g;    # strip the time elements
$header =~ s/(?:Expires|Date).*?GMT//g;    # strip the time elements
is( $sv, $header, 'header(\@vals) - complex header, 1' );

# cache() - scalar and array context, void argument
$sv = $q->cache();
is( $sv, undef, 'cache(), 1' );

# cache() - scalar and array context, true argument, sets no cache paragma
$sv = $q->cache( 1 );
is( $sv, 1, 'cache(1), 1' );
$sv = $q->header();
is( $sv =~ /Pragma: no-cache/, 1, 'cache(1), 2' );

# no_cache() - scalar and array context, void argument
$sv = $q->no_cache();
is( $sv, undef, 'cache(), 1' );

# no_cache() - scalar and array context, true argument, sets no cache paragma
$sv = $q->no_cache( 1 );
is( $sv, 1, 'cache(1), 1' );
$sv = $q->header();
like( $sv, qr/Pragma: no-cache/, 'cache(1), 2' );
ok( $sv =~ /Expires:(.*?)GMT/, 'cache(1), 3' );
ok( $sv =~ /Date:$1GMT/ . 'cache(1), 4' );

# redirect() - scalar and array context, void argument
$sv     = $q->redirect( 'http://a.galaxy.far.away.gov' );
$header = <<'HEADER';
Status: 302 Moved
Expires: Tue, 13 Nov 2001 06:45:15 GMT
Date: Tue, 13 Nov 2001 06:45:15 GMT
Pragma: no-cache
Location: http://a.galaxy.far.away.gov
HEADER
$sv     =~ s/[\012\015]//g;
$header =~ s/[\012\015]//g;
$sv     =~ s/(?:Expires|Date).*?GMT//g;    # strip the time elements
$header =~ s/(?:Expires|Date).*?GMT//g;    # strip the time elements
is( $sv, $header, 'redirect(), 1' );

# redirect() - scalar and array context, void argument
$sv = $q->redirect( -uri => 'http://a.galaxy.far.away.gov', -nph => 1 );
$header = <<'HEADER';
HTTP/1.0 302 Moved
Server: Apache - accept no substitutes
Status: 302 Moved
Expires: Tue, 13 Nov 2001 06:49:24 GMT
Date: Tue, 13 Nov 2001 06:49:24 GMT
Pragma: no-cache
Location: http://a.galaxy.far.away.gov
HEADER
$sv     =~ s/[\012\015]//g;
$header =~ s/[\012\015]//g;
$sv     =~ s/(?:Expires|Date).*?GMT//g;    # strip the time elements
$header =~ s/(?:Expires|Date).*?GMT//g;    # strip the time elements
is( $sv, $header, 'redirect() - nph, 1' );
################# Server Push Methods #################

$q = new CGI::Simple;

$sv = $q->multipart_init();
like(
  $sv,
  qr|Content-Type: multipart/x-mixed-replace;boundary="------- =_aaaaaaaaaa0"|,
  'multipart_init(), 1'
);
like( $sv, qr/--------- =_aaaaaaaaaa0$CRLF/, 'multipart_init(), 2' );
$sv = $q->multipart_init( 'this_is_the_boundary' );
like( $sv, qr/boundary="this_is_the_boundary"/, 'multipart_init(), 3' );
$sv = $q->multipart_init( -boundary => 'this_is_another_boundary' );
like(
  $sv,
  qr/boundary="this_is_another_boundary"/,
  'multipart_init(), 4'
);

# multipart_start()
$sv = $q->multipart_start();
is( $sv, "Content-Type: text/html$CRLF$CRLF", 'multipart_start(), 1' );
$sv = $q->multipart_start( 'foo/bar' );
is( $sv, "Content-Type: foo/bar$CRLF$CRLF", 'multipart_start(), 2' );
$sv = $q->multipart_start( -type => 'text/plain' );
is( $sv, "Content-Type: text/plain$CRLF$CRLF", 'multipart_start(), 3' );

# multipart_end()
$sv = $q->multipart_end();
is( $sv, "$CRLF--this_is_another_boundary$CRLF", 'multipart_end(), 1' );

# multipart_final() - scalar and array context, void/invalid/valid argument
$sv = $q->multipart_final();
like( $sv, qr|--this_is_another_boundary--|, 'multipart_final(), 1' );
################# Debugging Methods ################

# Dump() - scalar context, void argument
$sv = $q->Dump();
like( $sv, qr/JaPh,/, 'Dump(), 1' );

# as_string()
is( $q->as_string(), $q->Dump(), 'as_string(), 1' );

# cgi_error()
$ENV{'REQUEST_METHOD'} = 'GET';
$ENV{'QUERY_STRING'}   = '';
$q                     = CGI::Simple->new;

# we have changes this behaviour
# is( $q->cgi_error, qr/400 No data received via method: GET/ , 'cgi_error(), 1');
is( $q->cgi_error, undef, 'cgi_error(), 2' );
$ENV{'QUERY_STRING'} = 'name=JaPh%2C&color=red&color=green&color=blue';

############## cgi-lib.pl tests ################

# ReadParse() - scalar and array context, void/invalid/valid argument
CGI::Simple::ReadParse();
is( $in{'name'}, 'JaPh,', 'ReadParse(), 1' );
%in = ();
$q  = new CGI::Simple;
$q->ReadParse();
is( $in{'name'}, 'JaPh,', 'ReadParse(), 2' );
CGI::Simple::ReadParse( *field );
is( $field{'name'}, 'JaPh,', 'ReadParse(), 3' );
%field = ();
$q     = new CGI::Simple;
$q->ReadParse( *field );
is( $field{'name'}, 'JaPh,', 'ReadParse(), 4' );
$q = $field{'CGI'};
is( $q->param( 'name' ), 'JaPh,', 'ReadParse(), 5' );

# SplitParam() - scalar and array context, void/invalid/valid argument
is(
  join( ' ', $q->SplitParam( $field{'color'} ) ),
  'red green blue',
  'SplitParam(), 1'
);
is(
  join( ' ', CGI::Simple::SplitParam( $field{'color'} ) ),
  'red green blue',
  'SplitParam(), 2'
);
is( scalar $q->SplitParam( $field{'color'} ), 'red',
  'SplitParam(), 3' );
is( scalar CGI::Simple::SplitParam( $field{'color'} ),
  'red', 'SplitParam(), 4' );

# MethGet() - scalar and array context, void/invalid/valid argument
is( $q->MethGet, 1, 'MethGet(), 1' );

# MethPost() - scalar and array context, void/invalid/valid argument
is( !$q->MethPost, 1, 'MethPost(), 1' );

# MyBaseUrl() - scalar and array context, void/invalid/valid argument
is(
  $q->MyBaseUrl,
  'http://nowhere.com:8080/cgi-bin/foo.cgi',
  'MyBaseUrl(), 1'
);
$ENV{'SERVER_PORT'} = 80;
is(
  $q->MyBaseUrl,
  'http://nowhere.com/cgi-bin/foo.cgi',
  'MyBaseUrl(), 2'
);
$ENV{'SERVER_PORT'} = 8080;

# MyURL() - scalar and array context, void/invalid/valid argument
is( $q->MyURL, 'http://nowhere.com:8080/cgi-bin/foo.cgi',
  'MyURL(), 1' );

# MyFullUrl() - scalar and array context, void/invalid/valid argument
is(
  $q->MyFullUrl,
  'http://nowhere.com:8080/cgi-bin/foo.cgi/somewhere/else?name=JaPh%2C&color=red&color=green&color=blue',
  'MyFullUrl(), 1'
);
$ENV{'QUERY_STRING'} = '';
$ENV{'PATH_INFO'}    = '';
is(
  $q->MyFullUrl,
  'http://nowhere.com:8080/cgi-bin/foo.cgi',
  'MyFullUrl(), 2'
);
$ENV{'QUERY_STRING'} = 'name=JaPh%2C&color=red&color=green&color=blue';
$ENV{'PATH_INFO'}    = '/somewhere/else';

# PrintHeader() - scalar and array context, void/invalid/valid argument
like( $q->PrintHeader, qr|Content-Type: text/html|,
  'PrintHeader(), 1' );

# HtmlTop() - scalar and array context, void/invalid/valid argument
is(
  $q->HtmlTop( '$' ),
  "<html>\n<head>\n<title>\$</title>\n</head>\n<body>\n<h1>\$</h1>\n",
  'HtmlTop(), 1'
);
is(
  CGI::Simple::HtmlTop( '$' ),
  "<html>\n<head>\n<title>\$</title>\n</head>\n<body>\n<h1>\$</h1>\n",
  'HtmlTop(), 2'
);

# HtmlBot() - scalar and array context, void/invalid/valid argument
is( $q->HtmlBot, "</body>\n</html>\n", 'HtmlBot(), 1' );

# PrintVariables() - scalar and array context, void/invalid/valid argument
like( $q->PrintVariables( \%field ), qr/JaPh,/, 'PrintVariables(), 1' );

# PrintEnv() - scalar and array context, void/invalid/valid argument
like( $q->PrintEnv, qr/PATH_TRANSLATED/, 'PrintEnv(), 1' );

# CgiDie() - scalar and array context, void/invalid/valid argument

# CgiError() - scalar and array context, void/invalid/valid argument

################ Accessor Methods ################

$q = new CGI::Simple;

# version() - scalar context, void argument
like( $q->version(), qr/[\d\.]+/, 'version(), 1' );

# nph() - scalar context, void  argument
is( $q->nph(), $q->globals( 'NPH' ), 'nph(), 1' );

# nph() - scalar context, valid  argument
is( $q->nph( 42 ),        42, 'nph(42), 1' );
is( $q->globals( 'NPH' ), 42, 'nph(42), 2' );

# all_parameters() - array context, void/invalid/valid argument
$sv = $q->all_parameters();
@av = $q->all_parameters();
is( $sv, 2, 'all_parameters(), 1' );
is( join( ' ', @av ), 'name color', 'all_parameters(), 2' );

# charset() - scalar context, void argument
$sv = $q->charset();
is( $sv, 'utf-7', 'charset(), 1' );

# charset() - scalar context, void argument
$sv = $q->charset( 'Linear B' );
is( $sv, 'Linear B', 'charset(), 1' );
$sv = $q->charset();
is( $sv, 'Linear B', 'charset(), 2' );

# crlf() - scalar context, void argument
$sv = $q->crlf();
like( $sv, qr/[\012\015]{1,2}/, 'crlf(), 1' );

# globals() - scalar and array context, void argument
$sv = $q->globals();
is( $sv, 11, 'globals(), 1' );
@av = $q->globals();
is(
  join( ' ', sort @av ),
  'DEBUG DISABLE_UPLOADS FATAL HEADERS_ONCE NO_NULL NO_UNDEF_PARAMS NPH PARAM_UTF8 POST_MAX USE_CGI_PM_DEFAULTS USE_PARAM_SEMICOLONS',
  'globals(), 2'
);

# globals() - scalar context, invalid argument
$sv = $q->globals( 'FOO' );
is( $sv, undef, 'globals(\'FOO\') - invalid arg, 1' );

# globals() - scalar context, valid argument
is( $q->globals( 'VERSION', '3.1415' ),
  '3.1415', 'globals(\'VERSION\') - valid arg, 1' );
is( $q->globals( 'VERSION' ),
  '3.1415', 'globals(\'VERSION\') - valid arg, 2' );

# auth_type() - scalar context, void argument
$sv = $q->auth_type();
is( $sv, 'PGP MD5 DES rot13', 'auth_type(), 1' );

# content_length() - scalar context, void argument
$sv = $q->content_length();
is( $sv, '42', 'content_length(), 1' );

# content_type() - scalar context, void argument
$sv = $q->content_type();
is( $sv, 'application/x-www-form-urlencoded', 'content_type(), 1' );

# document_root() - scalar context, void argument
$sv = $q->document_root();
is( $sv, '/vs/www/foo', 'document_root(), 1' );

# gateway_interface() - scalar context, void argument
$sv = $q->gateway_interface();
is( $sv, 'bleeding edge', 'gateway_interface(), 1' );

# path_translated() - scalar context, void argument
$sv = $q->path_translated();
is( $sv, '/usr/local/somewhere/else', 'path_translated(), 1' );

# referer() - scalar context, void argument
$sv = $q->referer();
is( $sv, 'xxx.sex.com', 'referer(), 1' );

# remote_addr() - scalar and array context, void/invalid/valid argument
$sv = $q->remote_addr();
is( $sv, '127.0.0.1', 'remote_addr(), 1' );

# remote_host() - scalar context, void argument
$sv = $q->remote_host();
is( $sv, 'localhost', 'remote_host(), 1' );

# remote_ident() - scalar context, void argument
$sv = $q->remote_ident();
is( $sv, 'None of your damn business', 'remote_ident(), 1' );

# remote_user() - scalar context, void argument
$sv = $q->remote_user();
is( $sv, 'Just another Perl hacker,', 'remote_user(), 1' );

# request_method() - scalar context, void argument
$sv = $q->request_method();
is( $sv, 'GET', 'request_method(), 1' );

# script_name() - scalar context, void argument
$sv = $q->script_name();
is( $sv, '/cgi-bin/foo.cgi', 'script_name(), 1' );

# server_name() - scalar context, void argument
$sv = $q->server_name();
is( $sv, 'nowhere.com', 'server_name(), 1' );

# server_port() - scalar context, void argument
$sv = $q->server_port();
is( $sv, '8080', 'server_port(), 1' );

# server_protocol() - scalar context, void argument
$sv = $q->server_protocol();
is( $sv, 'HTTP/1.0', 'server_protocol(), 1' );

# server_software() - scalar context, void argument
$sv = $q->server_software();
is( $sv, 'Apache - accept no substitutes', 'server_software(), 1' );

# user_name() - scalar context, void argument
$sv = $q->user_name();
is( $sv, 'spammer@nowhere.com', 'user_name(), 1' );

# user_agent() - scalar context, void argument
$sv = $q->user_agent();
is( $sv, 'LWP', 'user_agent(), 1' );

# user_agent() - scalar context, void argument
$sv = $q->user_agent( 'lwp' );
is( $sv, 1, 'user_agent(), 1' );
$sv = $q->user_agent( 'mozilla' );
is( $sv, '', 'user_agent(), 2' );

# virtual_host() - scalar context, void argument
$sv = $q->virtual_host();
is( $sv, 'the.vatican.org', 'virtual_host(), 1' );

# path_info() - scalar and array context, void/valid argument
$sv = $q->path_info();
is( $sv, '/somewhere/else', 'path_info(), 1' );
$sv = $q->path_info( 'somewhere/else/again' );
is( $sv, '/somewhere/else/again', 'path_info(), 2' );
$sv = $q->path_info();
is( $sv, '/somewhere/else/again', 'path_info(), 3' );
$q->path_info( '/somewhere/else' );

# Accept() - scalar and array context, void argument
$sv = $q->Accept();
@av = $q->Accept();
is( $sv, 5, 'Accept(), 1' );
is(
  join( ' ', sort @av ),
  '*/* image/gif image/jpg text/html text/plain',
  'Accept(), 2'
);

# Accept() - scalar context, invalid argument (matches '*/*'
$sv = $q->Accept( 'foo/bar' );
is( $sv, '0.001', 'Accept(\'foo/bar\'), 1' );

# Accept() - scalar and array context, void argument
$sv = $q->Accept( '*/*' );
is( $sv, '0.001', 'Accept(), 1' );

# http() - scalar and array context, void argument
$sv = $q->http();
@av = $q->http();
ok( $sv > 0, 'http(), 1' );
like( $av[0], qr/HTTP/, 'http(), 2' );

# http() - scalar context, invalid arguments
$sv = $q->http( 'http-hell' );
is( $sv, undef, 'http(\'invalid arg\'), 1' );
$sv = $q->http( 'hell' );
is( $sv, undef, 'http(\'invalid arg\'), 2' );

# http() - scalar context, valid arguments
$sv = $q->http( 'http-from' );
is( $sv, 'spammer@nowhere.com', 'http(\'valid arg\'), 1' );
$sv = $q->http( 'from' );
is( $sv, 'spammer@nowhere.com', 'http(\'valid arg\'), 2' );

# https() - scalar and array context, void argument
$sv = $q->https();
is( $sv, 'ON', 'https(), 1' );

# https() - scalar  context, invalid argument
$sv = $q->https( 'hell' );
is( $sv, undef, 'https(\'invalid arg\'), 1' );

# https() - scalar context, valid arguments
$sv = $q->https( 'https-a' );
is( $sv, 'A', 'https(\'valid arg\'), 1' );
$sv = $q->https( 'a' );
is( $sv, 'A', 'https(\'valid arg\'), 2' );

# protocol() - scalar context, void arguments
$sv = $q->protocol();
is( $sv, 'https', 'protocol(), 1' );
$ENV{'HTTPS'}       = 'OFF';
$ENV{'SERVER_PORT'} = '443';
$sv                 = $q->protocol();
is( $sv, 'https', 'protocol(), 2' );
$ENV{'SERVER_PORT'} = '8080';
$sv = $q->protocol();
is( $sv, 'http', 'protocol(), 3' );

# url() - scalar context, void argument
$ENV{'HTTP_HOST'} = '';
is( $q->url, 'http://nowhere.com:8080/cgi-bin/foo.cgi', 'url(), 1' );

# url() - scalar context, valid argument
is( $q->url( -absolute => 1 ),
  '/cgi-bin/foo.cgi', 'CGI::url(-absolute=>1)' );

# url() - scalar context, valid argument
is( $q->url( -relative => 1 ), 'foo.cgi', 'url(-relative=>1), 1' );

# url() - scalar context, valid argument
is( $q->url( -relative => 1, -path => 1 ),
  'foo.cgi/somewhere/else', 'url(-relative=>1,-path=>1), 1' );

# url() - scalar context, valid argument
is(
  $q->url( -relative => 1, -path => 1, -query => 1 ),
  'foo.cgi/somewhere/else?name=JaPh%2C&color=red&color=green&color=blue',
  'url(-relative=>1,-path=>1,-query=>1), 1'
);

# self_url() - scalar context, void argument
$sv = $q->self_url();
@av = $q->self_url();
is(
  $sv,
  'http://nowhere.com:8080/cgi-bin/foo.cgi/somewhere/else?name=JaPh%2C&color=red&color=green&color=blue',
  'self_url(), 1'
);

# state() - scalar and array context, void/invalid/valid argument
is( $q->state(), $q->self_url(), 'state(), 1' );

################ Yet More Tests ################

#$CGI::Simple::POST_MAX = 20;
#$ENV{'REQUEST_METHOD'} = 'POST';
#$q = new CGI::Simple;
#is( $q->cgi_error, '413 Request entity too large: 42 bytes on STDIN exceeds $POST_MAX!' , 'Yet more tests, 1');

$ENV{'REQUEST_METHOD'} = 'HEAD';
$ENV{'QUERY_STRING'}   = '';
$ENV{'REDIRECT_QUERY_STRING'}
 = 'name=JAPH&color=red&color=green&color=blue';

$CGI::Simple::POST_MAX = 50;
$q                     = new CGI::Simple;
@av                    = $q->param;

is( join( ' ', @av ), 'name color', 'Yet more tests, 2' );
@av = $q->param( 'color' );
is( join( ' ', @av ), 'red green blue', 'Yet more tests, 3' );
