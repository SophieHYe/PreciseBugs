class Uke::Finder
  attr_accessor :q, :limit, :results, :results_voice, :results_digital, :location, :location_radius, :active_import

  def initialize
    @results = @results_voice = @results_digital = []

    @active_import = UkeImport.find_by_active(true)
    raise 'There is no active import' if active_import.nil?
  end

  def any?
    (@results.count > 0)
  end

  def query(value, limit = 2500)
    @q = value.to_s
    @limit = limit

    @results = nil
    @results = by_news
    @results = by_location  if @results.nil?
    @results = by_frq_range if @results.nil?
    @results = by_frq       if @results.nil?
    @results = by_string    if @results.nil?
    @results = []           if @results.nil?

    @results_voice   = @results.dup.keep_if{|station| Uke::Net::voice?(station[:net]) }
    @results_digital = @results.dup.keep_if{|station| Uke::Net::digital?(station[:net]) }

    self
  end

  def by_news
    return nil if @q.strip[0..4] != 'news:' || (@location = Geocoder.search(@q.gsub('news:', '').strip).first).nil?
    
    bounds_ne = @location.geometry['bounds']['northeast']
    bounds_sw = @location.geometry['bounds']['southwest']
    @location_radius = (distance_between_points([bounds_ne['lat'], bounds_ne['lng']], [bounds_sw['lat'], bounds_sw['lng']])/1000).round(0)
    
    sql = <<-SQL
          SELECT DISTINCT us.id,
                 (3959 * acos(cos(radians(:lat))*cos(radians(lat))*cos(radians(lon)-radians(:lon))+sin(radians(:lat))*sin(radians(lat)))) AS distance
            FROM uke_import_news n
      INNER JOIN uke_stations AS us ON (us.id = n.uke_station_id)
           WHERE n.uke_import_id = :uke_import_id
             AND lat BETWEEN :lat_sw AND :lat_ne
             AND lon BETWEEN :lon_sw AND :lon_ne
        ORDER BY distance ASC
    SQL
    
    sql.gsub!(':uke_import_id', @active_import.id.to_s)
    sql.gsub!(':lat_ne', conn.quote_string(bounds_ne['lat'].to_s))
    sql.gsub!(':lat_sw', conn.quote_string(bounds_sw['lat'].to_s))
    sql.gsub!(':lon_ne', conn.quote_string(bounds_ne['lng'].to_s))
    sql.gsub!(':lon_sw', conn.quote_string(bounds_sw['lng'].to_s))
    sql.gsub!(':lat', conn.quote_string(@location.latitude.to_s))
    sql.gsub!(':lon', conn.quote_string(@location.longitude.to_s))
    
    result_to_hash select_using_uke_stations_result(sql)
  end

  def by_location
    return nil if @q.strip[0..3] != 'loc:' || (@location = Geocoder.search(@q.gsub('loc:', '').strip).first).nil?

    bounds_ne = @location.geometry['bounds']['northeast']
    bounds_sw = @location.geometry['bounds']['southwest']
    @location_radius = (distance_between_points([bounds_ne['lat'], bounds_ne['lng']], [bounds_sw['lat'], bounds_sw['lng']])/1000).round(0)
    
    sql = <<-SQL
         SELECT DISTINCT us.id,
                (3959 * acos(cos(radians(:lat))*cos(radians(lat))*cos(radians(lon)-radians(:lon))+sin(radians(:lat))*sin(radians(lat)))) AS distance
           FROM uke_stations AS us
          WHERE us.uke_import_id = :uke_import_id
             AND lat BETWEEN :lat_sw AND :lat_ne
             AND lon BETWEEN :lon_sw AND :lon_ne
       ORDER BY distance ASC
    SQL

    sql.gsub!(':uke_import_id', @active_import.id.to_s)
    sql.gsub!(':lat_ne', conn.quote_string(bounds_ne['lat'].to_s))
    sql.gsub!(':lat_sw', conn.quote_string(bounds_sw['lat'].to_s))
    sql.gsub!(':lon_ne', conn.quote_string(bounds_ne['lng'].to_s))
    sql.gsub!(':lon_sw', conn.quote_string(bounds_sw['lng'].to_s))
    sql.gsub!(':lat', conn.quote_string(@location.latitude.to_s))
    sql.gsub!(':lon', conn.quote_string(@location.longitude.to_s))

    result_to_hash select_using_uke_stations_result(sql)
  end

  def by_frq_range
    return nil if @q.strip[0..3] != 'rng:' || (first = Uke::Unifier::frq_string(@q[4..@q.length].split('-').first)) < 1 || (last =  Uke::Unifier::frq_string(@q[4..@q.length].split('-').last)) < 1

    sql = <<-SQL
         SELECT DISTINCT fa.subject_id
           FROM frequencies f
     INNER JOIN frequency_assignments fa ON (fa.frequency_id = f.id AND fa.subject_type = 'UkeStation' AND fa.uke_import_id = :uke_import_id)
          WHERE (f.mhz BETWEEN :mhz_start AND :mhz_end)
    SQL

    result_to_hash select_using_uke_stations_sql(sql.gsub(':uke_import_id', @active_import.id.to_s).gsub(':mhz_start', conn.quote_string(first.to_s)).gsub(':mhz_end', conn.quote_string(last.to_s)))
  end

  def by_frq
    return nil if @q.length < 4 || Uke::Unifier::frq_string(@q) < 1

    sql = <<-SQL
         SELECT DISTINCT subject_id
           FROM frequencies f
     INNER JOIN frequency_assignments fa ON (fa.frequency_id = f.id AND fa.subject_type = 'UkeStation' AND fa.uke_import_id = :uke_import_id)
          WHERE f.mhz = :mhz
    SQL

    result_to_hash select_using_uke_stations_sql(sql.gsub(':uke_import_id', @active_import.id.to_s).gsub(':mhz', conn.quote_string(Uke::Unifier::frq_string(@q).to_s)))
  end

  def by_string
    return nil if @q.length < 4

    sql = <<-SQL
        SELECT us.id
          FROM uke_stations us
          JOIN uke_operators uo on (uo.id = us.uke_operator_id)
         WHERE us.uke_import_id = :uke_import_id
           AND (us.location LIKE '%:like%' OR us.name LIKE '%:like%' OR uo.name LIKE '%:like%')
    SQL

    result_to_hash select_using_uke_stations_sql(sql.gsub(':uke_import_id', @active_import.id.to_s).gsub(':like', conn.quote_string(@q)))
  end

  def by_frq_order_by_distance
    return nil if @location.nil? || @q.to_f == 0

    sql = <<-SQL
          SELECT uo.name AS owner,
                 CONCAT(us.name, ' ', us.location) AS display_name,
                 us.net,
                 us.radius,
                 us.lat,
                 us.lon,
                 (3959 * acos(cos(radians(:lat))*cos(radians(lat))*cos(radians(lon)-radians(:lon))+sin(radians(:lat))*sin(radians(lat)))) AS distance,
                 fa.id AS frequency_assignment_id
            FROM frequencies f
      INNER JOIN frequency_assignments fa ON (fa.frequency_id = f.id AND fa.subject_type = 'UkeStation' AND fa.uke_import_id = :uke_import_id)
      INNER JOIN uke_stations us ON us.id = fa.subject_id
      INNER JOIN uke_operators uo ON uo.id = us.uke_operator_id
           WHERE f.mhz = :mhz
             AND fa.usage = 'TX'
          HAVING distance <= 100
        ORDER BY distance ASC
    SQL

    result_to_hash(conn.select_all(sql.gsub(':uke_import_id', @active_import.id.to_s).gsub(':lat', conn.quote_string(@location.latitude.to_s)).gsub(':lon', conn.quote_string(@location.longitude.to_s)).gsub(':mhz', conn.quote_string(Uke::Unifier::frq_string(@q).to_s))))
  end

  private

  def conn
    ActiveRecord::Base.connection
  end

  def select_using_uke_stations_result(sql)
    result = conn.select_all sql
    return result unless result.rows.any?
    
    sids = result.rows.map{|row|row[0]}.join(',')
    select_using_uke_stations_sql(sids, "FIND_IN_SET(us.id, '#{sids}')")
  end

  def select_using_uke_stations_sql(stations_sql, sort = 'uo.name_unified')
    sql = <<-SQL
              SELECT uo.name AS owner,
                 us.name,
                 us.location,
                 us.net,
                 us.radius,
                 us.lat,
                 us.lon,
                 GROUP_CONCAT(DISTINCT f_tx.mhz) AS tx_frequencies,
                 GROUP_CONCAT(DISTINCT f_rx.mhz) AS rx_frequencies
            FROM uke_stations us
      INNER JOIN uke_operators uo            ON (uo.id = us.uke_operator_id)
       LEFT JOIN frequency_assignments fa_tx ON (fa_tx.subject_type = 'UkeStation' AND fa_tx.subject_id = us.id AND fa_tx.usage = 'TX')
       LEFT JOIN frequencies f_tx            ON (f_tx.id = fa_tx.frequency_id)
       LEFT JOIN frequency_assignments fa_rx ON (fa_rx.subject_type = 'UkeStation' AND fa_rx.subject_id = us.id AND fa_rx.usage = 'RX')
       LEFT JOIN frequencies f_rx            ON (f_rx.id = fa_rx.frequency_id)
           WHERE us.id IN (:stations_sql)
        GROUP BY us.id
        ORDER BY :sort
           LIMIT :limit
    SQL

    conn.select_all sql.gsub(':stations_sql', stations_sql).gsub(':sort', sort).gsub(':limit', @limit.to_s)
  end

  def result_to_hash(result)
    columns = result.columns.map{|column| column.to_sym}
    result.rows.map { |row| Hash[columns.zip(row)] }
  end
  
  def distance_between_points(a, b)
    rad_per_deg = Math::PI/180  # PI / 180
    rkm = 6371                  # Earth radius in kilometers
    rm = rkm * 1000             # Radius in meters
      
    dlon_rad = (b[1]-a[1]) * rad_per_deg  # Delta, converted to rad
    dlat_rad = (b[0]-a[0]) * rad_per_deg
          
    lat1_rad, lon1_rad = a.map! {|i| i * rad_per_deg }
    lat2_rad, lon2_rad = b.map! {|i| i * rad_per_deg }
            
    a = Math.sin(dlat_rad/2)**2 + Math.cos(lat1_rad) * Math.cos(lat2_rad) * Math.sin(dlon_rad/2)**2
    c = 2 * Math::atan2(Math::sqrt(a), Math::sqrt(1-a))
                  
    rm * c # Delta in meters
  end
end
