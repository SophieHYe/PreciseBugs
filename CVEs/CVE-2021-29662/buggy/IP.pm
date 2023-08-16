package Data::Validate::IP;

use strict;
use warnings;

use 5.008;

our $VERSION = '0.30';

use NetAddr::IP 4;
use Scalar::Util qw( blessed );

use base 'Exporter';

## no critic (Modules::ProhibitAutomaticExportation)
our @EXPORT = qw(
    is_ip
    is_ipv4
    is_ipv6
    is_innet_ipv4
);
## use critic

our $HAS_SOCKET;

BEGIN {
    local $@ = undef;
    $HAS_SOCKET = (!$ENV{DVI_NO_SOCKET})
        && eval {
        require Socket;
        Socket->import(qw( AF_INET AF_INET6 inet_pton ));

        # On some platforms, Socket.pm exports an inet_pton that just dies
        # when it is called. On others, inet_pton accepts various forms of
        # invalid input.
        defined &Socket::inet_pton
            && !defined inet_pton(Socket::AF_INET(),  '016.17.184.1')
            && !defined inet_pton(Socket::AF_INET6(), '2067::1:')

            # Some old versions of Socket are hopelessly broken
            && length(inet_pton(Socket::AF_INET(), '1.1.1.1')) == 4;
        };

    if ($HAS_SOCKET) {
        *is_ipv4             = \&_fast_is_ipv4;
        *is_ipv6             = \&_fast_is_ipv6;
        *is_ip               = \&_fast_is_ip;
        *_build_is_X_ip_subs = \&_build_fast_is_X_ip_subs;
    }
    else {
        *is_ipv4             = \&_slow_is_ipv4;
        *is_ipv6             = \&_slow_is_ipv6;
        *is_ip               = \&_slow_is_ip;
        *_build_is_X_ip_subs = \&_build_slow_is_X_ip_subs;
    }
}

sub new {
    my $class = shift;

    return bless {}, $class;
}

sub _fast_is_ip {
    shift if ref $_[0];
    my $value = shift;

    return undef unless defined $value;
    return $value =~ /:/ ? _fast_is_ipv6($value) : _fast_is_ipv4($value);
}

sub _fast_is_ipv4 {
    shift if ref $_[0];
    my $value = shift;

    return undef unless _fast_is_ipv4_packed($value);

    ## no critic (RegularExpressions::ProhibitCaptureWithoutTest)
    $value =~ /(.+)/;
    return $1;
}

sub _fast_is_ipv4_packed {
    my $value = shift;

    return undef unless defined $value;
    return undef if $value =~ /\0/;
    return inet_pton(Socket::AF_INET(), $value);
}

sub _slow_is_ip {
    shift if ref $_[0];
    my $value = shift;

    return _slow_is_ipv4($value) || _slow_is_ipv6($value);
}

sub _slow_is_ipv4 {
    shift if ref $_[0];
    my $value = shift;

    return undef unless defined($value);

    my (@octets) = $value =~ /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;
    return undef unless (@octets == 4);
    foreach (@octets) {
        return undef if $_ < 0 || $_ > 255;
        return undef if $_ =~ /^0\d{1,2}$/;
    }

    return join('.', @octets);
}

sub _fast_is_ipv6 {
    shift if ref $_[0];
    my $value = shift;

    return undef unless _fast_is_ipv6_packed($value);

    ## no critic (RegularExpressions::ProhibitCaptureWithoutTest)
    $value =~ /(.+)/;
    return $1;
}

sub _fast_is_ipv6_packed {
    my $value = shift;

    return undef unless defined $value;
    return undef if $value =~ /\0/;
    return undef if $value =~ /0[[:xdigit:]]{4}/;
    return inet_pton(Socket::AF_INET6(), $value);
}

{
    # This comes from Regexp::IPv6
    ## no critic (RegularExpressions::ProhibitComplexRegexes)
    my $ipv6_re
        = qr/(?-xism::(?::[0-9a-fA-F]{1,4}){0,5}(?:(?::[0-9a-fA-F]{1,4}){1,2}|:(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})))|[0-9a-fA-F]{1,4}:(?:[0-9a-fA-F]{1,4}:(?:[0-9a-fA-F]{1,4}:(?:[0-9a-fA-F]{1,4}:(?:[0-9a-fA-F]{1,4}:(?:[0-9a-fA-F]{1,4}:(?:[0-9a-fA-F]{1,4}:(?:[0-9a-fA-F]{1,4}|:)|(?::(?:[0-9a-fA-F]{1,4})?|(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2}))))|:(?:(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2}))|[0-9a-fA-F]{1,4}(?::[0-9a-fA-F]{1,4})?|))|(?::(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2}))|:[0-9a-fA-F]{1,4}(?::(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2}))|(?::[0-9a-fA-F]{1,4}){0,2})|:))|(?:(?::[0-9a-fA-F]{1,4}){0,2}(?::(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2}))|(?::[0-9a-fA-F]{1,4}){1,2})|:))|(?:(?::[0-9a-fA-F]{1,4}){0,3}(?::(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2}))|(?::[0-9a-fA-F]{1,4}){1,2})|:))|(?:(?::[0-9a-fA-F]{1,4}){0,4}(?::(?:(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2})[.](?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9]{1,2}))|(?::[0-9a-fA-F]{1,4}){1,2})|:)))/;

    sub _slow_is_ipv6 {
        shift if ref $_[0];
        my $value = shift;

        return undef unless defined($value);

        return '::' if $value eq '::';
        return undef unless $value =~ /^$ipv6_re$/;

        ## no critic (RegularExpressions::ProhibitCaptureWithoutTest)
        $value =~ /(.+)/;
        return $1;
    }
}

# This is just a quick test - we'll let NetAddr::IP decide if the address is
# valid.
my $ip_re         = qr/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/;
my $partial_ip_re = qr/\d{1,3}(?:\.\d{1,3}){0,2}/;

## no critic (Subroutines::ProhibitExcessComplexity, ControlStructures::ProhibitCascadingIfElse)
sub is_innet_ipv4 {
    shift if ref $_[0];
    my $value   = shift;
    my $network = shift;

    return undef unless defined($value);

    my $ip = is_ipv4($value);
    return undef unless defined $ip;

    # Backwards compatibility hacks to make it accept things that Net::Netmask
    # accepts.
    if (   $network eq 'default'
        || $network =~ /^$ip_re$/
        || $network =~ m{^$ip_re/\d\d?$}) {

        $network = NetAddr::IP->new($network) or return undef;
    }
    elsif (!(blessed $network && $network->isa('NetAddr::IP'))) {
        my $orig = $network;
        if ($network =~ /^($ip_re)[:\-]($ip_re)$/) {
            my ($net, $netmask) = ($1, $2);

            my $bits = _netmask_to_bits($netmask)
                or return undef;

            $network = "$net/$bits";
        }
        elsif ($network =~ /^($ip_re)\#($ip_re)$/) {
            my ($net, $hostmask) = ($1, $2);

            my $bits = _hostmask_to_bits($hostmask)
                or return undef;

            $network = "$net/$bits";
        }
        elsif ($network =~ m{^($partial_ip_re)/(\d\d?)$}) {
            my ($net, $bits) = ($1, $2);

            # This is a hack to avoid a deprecation warning (Use of implicit
            # split to @_ is deprecated) that shows up on 5.10.1 but not on
            # newer Perls.
            #
            ## no critic(Variables::ProhibitUnusedVarsStricter)
            my $octets = scalar(my @tmp = split /\./, $net);
            $network = $net;
            $network .= '.0' x (4 - $octets);
            $network .= "/$bits";
        }
        elsif ($network =~ /^$partial_ip_re$/) {

            ## no critic(Variables::ProhibitUnusedVarsStricter)
            my $octets = scalar(my @tmp = split /\./, $network);
            if ($octets < 4) {
                $network .= '.0' x (4 - $octets);
                $network .= '/' . $octets * 8;
            }
        }

        if ($orig ne $network) {
            _deprecation_warn(
                'Use of non-CIDR notation for networks with is_innet_ipv4() is deprecated'
            );
        }

        $network = NetAddr::IP->new($network) or return undef;
    }

    my $netaddr_ip = NetAddr::IP->new($ip) or return undef;

    return $ip if $network->contains($netaddr_ip);
    return undef;
}
## use critic;

{
    my %netmasks = (
        '128.0.0.0'       => '1',
        '192.0.0.0'       => '2',
        '224.0.0.0'       => '3',
        '240.0.0.0'       => '4',
        '248.0.0.0'       => '5',
        '252.0.0.0'       => '6',
        '254.0.0.0'       => '7',
        '255.0.0.0'       => '8',
        '255.128.0.0'     => '9',
        '255.192.0.0'     => '10',
        '255.224.0.0'     => '11',
        '255.240.0.0'     => '12',
        '255.248.0.0'     => '13',
        '255.252.0.0'     => '14',
        '255.254.0.0'     => '15',
        '255.255.0.0'     => '16',
        '255.255.128.0'   => '17',
        '255.255.192.0'   => '18',
        '255.255.224.0'   => '19',
        '255.255.240.0'   => '20',
        '255.255.248.0'   => '21',
        '255.255.252.0'   => '22',
        '255.255.254.0'   => '23',
        '255.255.255.0'   => '24',
        '255.255.255.128' => '25',
        '255.255.255.192' => '26',
        '255.255.255.224' => '27',
        '255.255.255.240' => '28',
        '255.255.255.248' => '29',
        '255.255.255.252' => '30',
        '255.255.255.254' => '31',
        '255.255.255.255' => '32',
    );

    sub _netmask_to_bits {
        return $netmasks{ $_[0] };
    }
}

{
    my %hostmasks = (
        '255.255.255.255' => 0,
        '127.255.255.255' => 1,
        '63.255.255.255'  => 2,
        '31.255.255.255'  => 3,
        '15.255.255.255'  => 4,
        '7.255.255.255'   => 5,
        '3.255.255.255'   => 6,
        '1.255.255.255'   => 7,
        '0.255.255.255'   => 8,
        '0.127.255.255'   => 9,
        '0.63.255.255'    => 10,
        '0.31.255.255'    => 11,
        '0.15.255.255'    => 12,
        '0.7.255.255'     => 13,
        '0.3.255.255'     => 14,
        '0.1.255.255'     => 15,
        '0.0.255.255'     => 16,
        '0.0.127.255'     => 17,
        '0.0.63.255'      => 18,
        '0.0.31.255'      => 19,
        '0.0.15.255'      => 20,
        '0.0.7.255'       => 21,
        '0.0.3.255'       => 22,
        '0.0.1.255'       => 23,
        '0.0.0.255'       => 24,
        '0.0.0.127'       => 25,
        '0.0.0.63'        => 26,
        '0.0.0.31'        => 27,
        '0.0.0.15'        => 28,
        '0.0.0.7'         => 29,
        '0.0.0.3'         => 30,
        '0.0.0.1'         => 31,
        '0.0.0.0'         => 32,
    );

    sub _hostmask_to_bits {
        return $hostmasks{ $_[0] };
    }
}

{
    my %warned_at;

    sub _deprecation_warn {
        my $warning = shift;
        my @caller  = caller(2);

        my $caller_info
            = "at line $caller[2] of $caller[0] in sub $caller[3]";

        return undef if $warned_at{$warning}{$caller_info}++;

        warn "$warning $caller_info\n";
    }
}

{
    my %ipv4_networks = (
        loopback => { networks => '127.0.0.0/8' },
        private  => {
            networks => [
                qw(
                    10.0.0.0/8
                    172.16.0.0/12
                    192.168.0.0/16
                )
            ],
        },
        testnet => {
            networks => [
                qw(
                    192.0.2.0/24
                    198.51.100.0/24
                    203.0.113.0/24
                )
            ],
        },
        anycast    => { networks => '192.88.99.0/24' },
        multicast  => { networks => '224.0.0.0/4' },
        linklocal  => { networks => '169.254.0.0/16' },
        unroutable => {
            networks => [
                qw(
                    0.0.0.0/8
                    100.64.0.0/10
                    192.0.0.0/29
                    198.18.0.0/15
                    240.0.0.0/4
                )
            ],
        },
    );

    _build_is_X_ip_subs(\%ipv4_networks, 4);
}

{
    my %ipv6_networks = (
        loopback    => { networks => '::1/128' },
        ipv4_mapped => { networks => '::ffff:0:0/96' },
        discard     => { networks => '100::/64' },
        special     => { networks => '2001::/23' },
        teredo      => {
            networks  => '2001::/32',
            subnet_of => 'special',
        },
        orchid => {
            networks  => '2001:10::/28',
            subnet_of => 'special',
        },
        documentation => { networks => '2001:db8::/32' },
        private       => { networks => 'fc00::/7' },
        linklocal     => { networks => 'fe80::/10' },
        multicast     => { networks => 'ff00::/8' },
    );

    _build_is_X_ip_subs(\%ipv6_networks, 6);

    # This exists for the benefit of the test code.
    ## no critic (Subroutines::ProhibitUnusedPrivateSubroutines)
    sub _network_is_subnet_of {
        my $network = shift;
        my $other   = shift;

        return ($ipv6_networks{$network}{subnet_of} || q{}) eq $other;
    }
}

## no critic (TestingAndDebugging::ProhibitNoStrict, BuiltinFunctions::ProhibitStringyEval)
sub _build_slow_is_X_ip_subs {
    my $networks  = shift;
    my $ip_number = shift;

    my $is_ip_sub   = $ip_number == 4 ? 'is_ipv4' : 'is_ipv6';
    my $netaddr_new = $ip_number == 4 ? 'new'     : 'new6';

    my @all_nets;

    local $@ = undef;
    for my $type (keys %{$networks}) {
        my @nets
            = map { NetAddr::IP->$netaddr_new($_) }
            ref $networks->{$type}{networks}
            ? @{ $networks->{$type}{networks} }
            : $networks->{$type}{networks};

        # Some IPv6 networks (like TEREDO) are a subset of the special block
        # so there's no point in checking for them in the is_public_ipv6()
        # sub.
        unless ($networks->{$type}{subnet_of}) {
            push @all_nets, @nets;
        }

        # We're using code gen rather than just making an anon sub outright so
        # we don't have to pay the cost of derefencing the $is_ip_sub and the
        # dynamic dispatch cost for $netaddr_new
        my $sub = eval sprintf( <<'EOF', $is_ip_sub, $netaddr_new);
sub {
    shift if ref $_[0];
    my $value = shift;

    return undef unless defined $value;

    my $ip = %s($value);
    return undef unless defined $ip;

    my $netaddr_ip = NetAddr::IP->%s($ip);
    for my $net (@nets) {
        return $ip if $net->contains($netaddr_ip);
    }
    return undef;
}
EOF
        die $@ if $@;

        my $sub_name = 'is_' . $type . '_ipv' . $ip_number;
        {
            no strict 'refs';
            *{$sub_name} = $sub;
        }
        push @EXPORT, $sub_name;
    }

    my $sub = eval sprintf( <<'EOF', $is_ip_sub, $netaddr_new);
sub {
    shift if ref $_[0];
    my $value = shift;

    return undef unless defined($value);

    my $ip = %s($value);
    return undef unless defined $ip;

    my $netaddr_ip = NetAddr::IP->%s($ip);
    for my $net (@all_nets) {
        return undef if $net->contains($netaddr_ip);
    }

    return $ip;
}
EOF
    die $@ if $@;

    my $sub_name = 'is_public_ipv' . $ip_number;
    {
        no strict 'refs';
        *{$sub_name} = $sub;
    }
    push @EXPORT, $sub_name;
}

sub _build_fast_is_X_ip_subs {
    my $networks  = shift;
    my $ip_number = shift;

    my $family = $ip_number == 4 ? Socket::AF_INET() : Socket::AF_INET6();

    my @all_nets;

    local $@ = undef;
    for my $type (keys %{$networks}) {
        my @nets
            = map { _packed_network_and_netmask($family, $_) }
            ref $networks->{$type}{networks}
            ? @{ $networks->{$type}{networks} }
            : $networks->{$type}{networks};

        # Some IPv6 networks (like TEREDO) are a subset of the special block
        # so there's no point in checking for them in the is_public_ipv6()
        # sub.
        unless ($networks->{$type}{subnet_of}) {
            push @all_nets, @nets;
        }

        # We're using code gen rather than just making an anon sub outright so
        # we don't have to pay the cost of derefencing the $is_ip_sub and the
        # dynamic dispatch cost for $netaddr_new
        my $sub = eval sprintf( <<'EOF', $ip_number);
sub {
    shift if ref $_[0];
    my $value = shift;

    my $ip = _fast_is_ipv%u_packed($value);

    return undef unless defined $ip;

    for my $net (@nets) {
        if (($net->[1] & $ip) eq $net->[0]) {
            $value =~ /(.+)/;
            return $1;
        }
    }
    return undef;
}
EOF
        die $@ if $@;

        my $sub_name = 'is_' . $type . '_ipv' . $ip_number;
        {
            no strict 'refs';
            *{$sub_name} = $sub;
        }
        push @EXPORT, $sub_name;
    }

    my $sub = eval sprintf( <<'EOF', $ip_number);
sub {
    shift if ref $_[0];
    my $value = shift;

    my $ip = _fast_is_ipv%u_packed($value);

    return undef unless defined $ip;

    for my $net (@all_nets) {
        return undef if ($net->[1] & $ip) eq $net->[0];
    }

    $value =~ /(.+)/;
    return $1;
}
EOF
    die $@ if $@;

    my $sub_name = 'is_public_ipv' . $ip_number;
    {
        no strict 'refs';
        *{$sub_name} = $sub;
    }
    push @EXPORT, $sub_name;
}

sub _packed_network_and_netmask {
    my $family  = shift;
    my $network = shift;

    my ($ip, $bits) = split qr{/}, $network, 2;

    return [
        inet_pton($family, $ip),
        _packed_netmask($family, $bits)
    ];
}

sub _packed_netmask {
    my $family = shift;
    my $bits   = shift;

    my $bit_length = $family == Socket::AF_INET() ? 32 : 128;

    my $bit_string
        = join(q{}, (1) x $bits, (0) x ($bit_length - $bits));
    return pack('B' . $bit_length, $bit_string);
}

for my $sub (qw( linklocal loopback multicast private public )) {
    my $sub_name = "is_${sub}_ip";

    {
        no strict 'refs';
        *{$sub_name}
            = eval "sub { ${sub_name}v4(\@_) || ${sub_name}v6(\@_) }";
        die $@ if $@;
    }

    push @EXPORT, $sub_name;
}
## use critic

1;

# ABSTRACT: IPv4 and IPv6 validation methods

__END__

=pod

=head1 SYNOPSIS

  use Data::Validate::IP qw(is_ipv4 is_ipv6);

  my $suspect = '1.2.3.4';
  if (is_ipv4($suspect)) {
      print "Looks like an IPv4 address";
  }
  else {
      print "Not an IPv4 address\n";
  }

  $suspect = '::1234';
  if (is_ipv6($suspect)) {
      print "Looks like an IPv6 address";
  }
  else {
      print "Not an IPv6 address\n";
  }

=head1 DESCRIPTION

This module provides a number IP address validation subs that both validate
and untaint their input. This includes both basic validation (C<is_ipv4()> and
C<is_ipv6()>) and special cases like checking whether an address belongs to a
specific network or whether an address is public or private (reserved).

=head1 USAGE AND SECURITY RECOMMENDATIONS

It's important to understand that if C<is_ipv4($ip)> or C<is_ipv6($ip)> return
false, then all other validation functions for that IP address family will
I<also> return false. So for example, if C<is_ipv4($ip)> is false, so are both
C<is_private_ipv4($ip)> I<and> C<is_public_ipv4($ip)>.

This means that simply calling C<is_private_ipv4($ip)> by itself is not
sufficient if you are dealing with untrusted input. You should always check
C<is_ipv4($ip)> as well.

There are security implications to this around certain oddly formed
addresses. Notably, an address like "010.0.0.1" is technically valid, but the
operating system will treat "010" as an octal number. That means that
"010.0.0.1" is equivalent to "8.0.0.1", I<not> "10.0.0.1".

However, this module's C<is_ipv4($ip)> function will return false for
addresses like "010.0.0.1" which have octal components. And of course that
means that it also returns false for C<is_private_ipv4($ip)> I<and>
C<is_public_ipv4($ip)>.

=head1 FUNCTIONS

All of the functions below are exported by default.

All functions return an untainted value if the test passes and undef if it
fails. In theory, this means that you should always check for a defined status
explicitly but in practice there are no valid IP addresses where the string
form evaluates to false in Perl.

Note that none of these functions actually attempt to test whether the given
IP address is routable from your device; they are purely semantic checks.

=head2 is_ipv4($ip), is_ipv6($ip), is_ip($ip)

These functions simply check whether the address is a valid IPv4 or IPv6 address.

=head2 is_innet_ipv4($ip, $network)

This subroutine checks whether the address belongs to the given IPv4
network. The C<$network> argument can either be a string in CIDR notation like
"15.0.15.0/24" or a L<NetAddr::IP> object.

This subroutine used to accept many more forms of network specifications
(anything L<Net::Netmask> accepts) but this has been deprecated.

=head2 is_unroutable_ipv4($ip)

This subroutine checks whether the address belongs to any of several special
use IPv4 networks - C<0.0.0.0/8>, C<100.64.0.0/10>, C<192.0.0.0/29>,
C<198.18.0.0/15>, C<240.0.0.0/4> - as defined by L<RFC
5735|http://tools.ietf.org/html/rfc5735>, L<RFC
6333|http://tools.ietf.org/html/rfc6333>, and L<RFC
6958|http://tools.ietf.org/html/rfc6598>.

Arguably, these should be broken down further but this subroutine will always
exist for backwards compatibility.

=head2 is_private_ipv4($ip)

This subroutine checks whether the address belongs to any of the private IPv4
networks - C<10.0.0.0/8>, C<172.16.0.0/12>, C<192.168.0.0/16> - as defined by
L<RFC 5735|http://tools.ietf.org/html/rfc5735>.

=head2 is_loopback_ipv4($ip)

This subroutine checks whether the address belongs to the IPv4 loopback
network - C<127.0.0.0/8> - as defined by L<RFC
5735|http://tools.ietf.org/html/rfc5735>.

=head2 is_linklocal_ipv4($ip)

This subroutine checks whether the address belongs to the IPv4 link local
network - C<169.254.0.0/16> - as defined by L<RFC
5735|http://tools.ietf.org/html/rfc5735>.

=head2 is_testnet_ipv4($ip)

This subroutine checks whether the address belongs to any of the IPv4 TEST-NET
networks for use in documentation and example code - C<192.0.2.0/24>,
C<198.51.100.0/24>, and C<203.0.113.0/24> - as defined by L<RFC
5735|http://tools.ietf.org/html/rfc5735>.

=head2 is_anycast_ipv4($ip)

This subroutine checks whether the address belongs to the 6to4 relay anycast
network - C<192.88.99.0/24> - as defined by L<RFC
5735|http://tools.ietf.org/html/rfc5735>.

=head2 is_multicast_ipv4($ip)

This subroutine checks whether the address belongs to the IPv4 multicast
network - C<224.0.0.0/4> - as defined by L<RFC
5735|http://tools.ietf.org/html/rfc5735>.

=head2 is_loopback_ipv6($ip)

This subroutine checks whether the address is the IPv6 loopback address -
C<::1/128> - as defined by L<RFC 4291|http://tools.ietf.org/html/rfc4291>.

=head2 is_ipv4_mapped_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 IPv4-mapped
address network - C<::ffff:0:0/96> - as defined by L<RFC
4291|http://tools.ietf.org/html/rfc4291>.

=head2 is_discard_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 discard prefix
network - C<100::/64> - as defined by L<RFC
6666|http://tools.ietf.org/html/rfc6666>.

=head2 is_special_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 special network
- C<2001::/23> - as defined by L<RFC 2928|http://tools.ietf.org/html/rfc2928>.

=head2 is_teredo_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 TEREDO network
- C<2001::/32> - as defined by L<RFC 4380|http://tools.ietf.org/html/rfc4380>.

Note that this network is a subnet of the larger special network at
C<2001::/23>.

=head2 is_orchid_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 ORCHID network
- C<2001::/32> - as defined by L<RFC 4380|http://tools.ietf.org/html/rfc4380>.

Note that this network is a subnet of the larger special network at
C<2001::/23>.

This network is currently scheduled to be returned to the special pool in
March of 2014 unless the IETF extends its use. If that happens this subroutine
will continue to exist but will always return false.

=head2 is_documentation_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 documentation
network - C<2001:DB8::/32> - as defined by L<RFC
3849|http://tools.ietf.org/html/rfc3849>.

=head2 is_private_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 private network
- C<FC00::/7> - as defined by L<RFC 4193|http://tools.ietf.org/html/rfc4193>.

=head2 is_linklocal_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 link-local
unicast network - C<FE80::/10> - as defined by L<RFC
4291|http://tools.ietf.org/html/rfc4291>.

=head2 is_multicast_ipv6($ip)

This subroutine checks whether the address belongs to the IPv6 multicast
network - C<FF00::/8> - as defined by L<RFC
4291|http://tools.ietf.org/html/rfc4291>.

=head2 is_public_ipv4($ip), is_public_ipv6($ip), is_public_ip($ip)

These subroutines check whether the given IP address belongs to any of the
special case networks defined previously. Note that this is B<not> simply the
opposite of checking C<is_private_ipv4()> or C<is_private_ipv6()>. The private
networks are a subset of all the special case networks.

=head2 is_linklocal_ip($ip)

This subroutine checks whether the address belongs to the IPv4 or IPv6
link-local unicast network.

=head2 is_loopback_ip($ip)

This subroutine checks whether the address is the IPv4 or IPv6 loopback
address.

=head2 is_multicast_ip($ip)

This subroutine checks whether the address belongs to the IPv4 or IPv6
multicast network.

=head2 is_private_ip($ip)

This subroutine checks whether the address belongs to the IPv4 or IPv6 private
network.

=for Pod::Coverage new

=head1 OBJECT-ORIENTED INTERFACE

This module can also be used as a class. You can call C<<
Data::Validate::IP->new() >> to get an object and then call any of the
validation subroutines as methods on that object. This is somewhat pointless
since the object will never contain any state but this interface is kept for
backwards compatibility.

=head1 SEE ALSO

IPv4

B<[RFC 5735] [RFC 1918]>

IPv6

B<[RFC 2460] [RFC 4193] [RFC 4291] [RFC 6434]>

=head1 BUGS

Please report any bugs or feature requests to
C<bug-data-validate-ip@rt.cpan.org>, or through the web interface at
L<http://rt.cpan.org>. I will be notified, and then you'll automatically be
notified of progress on your bug as I make changes.

=head1 ACKNOWLEDGEMENTS

Thanks to Richard Sonnen <F<sonnen@richardsonnen.com>> for writing the
Data::Validate module.

Thanks to Matt Dainty <F<matt@bodgit-n-scarper.com>> for adding the
C<is_multicast_ipv4()> and C<is_linklocal_ipv4()> code.

=cut
