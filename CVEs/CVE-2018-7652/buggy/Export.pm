package Zonemaster::GUI::Dancer::Export;

use warnings;
use 5.14.2;

use Dancer ':syntax';
use Plack::Builder;
use HTML::Entities;
use Zonemaster::GUI::Dancer::Client;

our $VERSION = '1.0.7';

my $backend_port = 5000;
$backend_port = $ENV{ZONEMASTER_BACKEND_PORT} if ($ENV{ZONEMASTER_BACKEND_PORT});
my $url = "http://localhost:$backend_port";

any [ 'get', 'post' ] => '/export' => sub {
    header( 'Cache-Control' => 'no-store, no-cache, must-revalidate' );
    my %allparams = params;
    no warnings 'uninitialized';

    if ( $allparams{'type'} eq 'HTML' ) {
		my $c = Zonemaster::GUI::Dancer::Client->new( { url => $url } );
		my $test_result = $c->get_test_results( { id => $allparams{'test_id'}, language => $allparams{'lang'} } );

		my @test_results;
		my $previous_module = '';
		foreach my $result ( @{ $test_result->{results} } ) {
			if ( $previous_module ne $result->{module} ) {
				push( @test_results, { is_module => 1, message => $result->{module} } );
				$previous_module = $result->{module};
			}

			push( @test_results, { is_module => 0, message => $result->{message}, class => "alert alert-$result->{level}" } );
		}

		my $template_params;
		$template_params->{test_results}  = \@test_results;
		template 'export', $template_params, { layout => undef };
    }
};

true;
