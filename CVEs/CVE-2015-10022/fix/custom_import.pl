#!/usr/bin/perl

use vars qw/$libpath/;
use FindBin qw($Bin);
BEGIN { $libpath="$Bin" };
use lib "$libpath";
use lib "$libpath/../libs";

use DB_File;
use DBI;
$| = 1;

my %dbconfig = loadconfig("/etc/apache2/nlgiss2.config");
$site = $dbconfig{root};
my ($dbname, $dbhost, $dblogin, $dbpassword) = ($dbconfig{customdbname}, $dbconfig{dbhost}, $dbconfig{dblogin}, $dbconfig{dbpassword});
my $dbh = DBI->connect("dbi:Pg:dbname=$dbname;host=$dbhost",$dblogin,$dbpassword,{AutoCommit=>1,RaiseError=>1,PrintError=>0});

my $sqlstructure = "cbsnr, naam, year, code, indicator, value, amsterdam_code";
my @stritems = split(/\,\s*/, $sqlstructure);
$id = 0;
foreach $item (@stritems)
{
   $structure{$item} = $id; 
   $id++;
}

$lineID = 0;
while (<>)
{
    # OUTPUT INDUSTRY 3.01: Industrial output in rubles
    my $str = $_;
    # Prevention from sql injection
    $sqlinjection = 0;
    $sqlinjection++ if ($str=~/(drop.+all|drop.+table)/sxi);
    $sqlinjection++ if ($str=~/(alter|create).+table/sxi);
    $sqlinjection++ if ($str=~/^select/sxi);
    exit(0) if ($sqlinjection);
    if ($str!~/^\".+?\"/)
    {
       $str = transform($str);
    }
    $str=~s/\r|\n//g;
    $str.=",";
    my $itemID = 0;
    my %thisdata;
    while ($str=~s/^\"(.*?)\"\,//)
    {
	my $item = $1;
	unless ($lineID)
	{
	    $names{$itemID} = $item;
	}
	else
	{
	    $data{$lineID}{$names{$itemID}} = $item;
	    $thisdata{$lineID}{$names{$itemID}} = $item;
	}
	$itemID++;
    }

    unless ($lineID)
    {
	%rnames = reverse %names;
    }
    else
    {
        unless ($rnames{'value'})
        {
	    $values{$thisdata{$lineID}{'year'}}{$thisdata{$lineID}{'amsterdam_code'}}++;
        }

	$topics{$thisdata{$lineID}{'code'}} = $thisdata{$lineID}{'indicator'};
    }
    $lineID++;
}

# Aggregation
foreach $lineID (sort keys %data)
{
   %items = %{$data{$lineID}};
   my ($code, $year) = ($items{'amsterdam_code'}, $items{'year'});
   print "I $code $year $values{$year}{$code}\n" if ($DEBUG);
   foreach $name (sort keys %names)
   {
	print "$names{$name};;$items{$name}\n" if ($DEBUG);
   }

   unless ($added{$year}{$code})
   {
      $sql = "insert into datasets.data ($sqlstructure) values (";
      $items{'value'} = $values{$year}{$code} || '0';
      foreach $item (@stritems)
      {
	 $var = $items{$item} || '0';
	 $dbhitem = $dbh->quote($var);
	 $sql.="$dbhitem,"
	 #print "$item $items{$item}\n";
      }

      $sql=~s/\,$//g;
      $sql.=");";
      #print "$sql\n";
      $dbh->do($sql);
   }

   $added{$year}{$code}++;
   # $sql = "insert into datasets.data (cbsnr, naam, year, code, indicator, value, amsterdam_code) values ('$cbsnr', $naamq, '$year', '$mcode', $indicator, '$items[$i]', '$acode');";
   #parser($str);
}

foreach $topic (sort %topics)
{
    if ($topics{$topic})
    {
       $name = $dbh->quote("$topics{$topic}");
       $insert = "insert into datasets.topics (topic_name, topic_code, datatype, topic_root, description, topic_name_rus) values ($name, '$topic', '0', '0', ' ', ' ')";
       $dbh->do($insert);
       #print "$insert\n";
    }
}

sub loadconfig
{
    my ($configfile, $DEBUG) = @_;
    my %config;

    open(conf, $configfile);
    while (<conf>)
    {
        my $str = $_;
        $str=~s/\r|\n//g;
        my ($name, $value) = split(/\s*\=\s*/, $str);
        $config{$name} = $value;
    }
    close(conf);

    return %config;
}

sub transform
{
   my ($str, $DEBUG) = @_;
   $str=~s/\r|\n//g;
   my @items = split(/\,/, $str);
   my $line;
   foreach $item (@items)
   {
      $line.="\"$item\",";
   }
   $line=~s/\,$//g;

   return $line;
}
