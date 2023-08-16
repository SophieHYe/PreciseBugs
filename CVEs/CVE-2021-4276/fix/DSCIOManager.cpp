/* 
 * Copyright 2014, 2015, 2016 Internet Corporation for Assigned Names and Numbers.
 * 
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, you can obtain one at https://mozilla.org/MPL/2.0/.
 */

/*
 * Developed by Sinodun IT (www.sinodun.com)
 */

/* 
 * File:   xml_files.cpp
 */

#define BOOST_FILESYSTEM_NO_DEPRECATED

#include <exception>
#include <iostream>
#include <algorithm>
#include <vector>
#include <exception>
#include <time.h>

#include <boost/property_tree/ptree.hpp>
#include <boost/property_tree/xml_parser.hpp>
#include <boost/foreach.hpp>
#include <boost/filesystem.hpp>

#include <pqxx/pqxx>

#include "DSCIOManager.h"
#include "DSCDataManager.h"
#include "dsc_types.h"
#include "DSCStrategyFactory.h"

#include "config.h"

using namespace std;
namespace bfs = boost::filesystem;

// Helper function
// Get current date/time, format is YYYY-MM-DD HH:mm:ss time-zone
const std::string currentDateTime() {

    time_t     now = time(0);
    struct tm  tstruct;
    char       buf[80];
    tstruct = *localtime(&now);
    strftime(buf, sizeof(buf), "%Y-%m-%d %X %Z", &tstruct);

    return buf;

}

void replace_string(std::string& str, const std::string& from, const std::string& to) {
    if(from.empty())
        return;
    size_t start_pos = 0;
    while((start_pos = str.find(from, start_pos)) != std::string::npos) {
        str.replace(start_pos, from.length(), to);
        start_pos += to.length(); // In case 'to' contains 'from', like replacing 'x' with 'yx'
    }
}


DSCIOManager::DSCIOManager(const enum INPUT_OUTPUT input, const enum INPUT_OUTPUT output, const string& conn_string, const string& processing_start_date_string, bool rssac) {

    input_ = input;
    output_ = output;
    conn_string_ = conn_string;
    pg_db_conn_ = NULL;
    pg_db_trans_ = NULL;
    processing_start_date_ = NULL;
    rssac_=rssac;

    cout << endl << endl << "----------------------- " << currentDateTime() << " ----------------------" << endl;
    // work out if we have a valid start date
    struct tm p_start_date  = { 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL };
    if ( ! processing_start_date_string.empty() ) {
       cout << "Processing start date defined as " << processing_start_date_string << endl; 
       if ( strptime(processing_start_date_string.c_str(), "%Y-%m-%d", &p_start_date) != NULL ) {   
                processing_start_date_ = new time_t(mktime(&p_start_date));
                //cout << "Time is: " << p_start_date.tm_year << " " << p_start_date.tm_mon << " " << p_start_date.tm_mday << " " << p_start_date.tm_hour << " " << p_start_date.tm_min << " " << p_start_date.tm_sec << endl;   
       } else {
           cerr << "Error: Invalid processing start date \'" << processing_start_date_string << "\' provided. Must be YYYY-MM-DD format. Exiting." << endl;
           exit(1);
       }
    } 
}

DSCIOManager::~DSCIOManager() {
    
    cout << "----------------------- " << currentDateTime() << " -----------------------" << endl << endl;
    if ( processing_start_date_ != NULL ) {
        delete processing_start_date_;
    }
}
    
int
DSCIOManager::dsc_connect_pg_db() {
    pg_db_conn_ = new pqxx::connection( conn_string_ );
    if ( pg_db_conn_ == NULL ) {
        cerr << "Error connecting to database." << endl;
        exit(1);
    }
    pg_db_conn_->activate();
    
    /* Check Database version */
    int db_version = 0;
    try {
        pg_db_trans_ = new pqxx::work( *pg_db_conn_ );
        if ( pg_db_trans_ == NULL ) {
            cerr << "Error starting transaction." << endl;
            exit(1);
        }
        const pqxx::result R = pg_db_trans_->exec("select version from version;");
        pqxx::result::const_iterator row;
        for (row = R.begin(); row != R.end(); ++row) {
            row["version"].to(db_version);
        }
        if ( db_version != DB_SCHEMA_VERSION ) {
            cout << "Database version is not supported. Expected: " << DB_SCHEMA_VERSION << " Found: " << db_version << endl;
            exit( EXIT_FAILURE);
        }
        if ( pg_db_trans_ != NULL ) {
            pg_db_trans_->commit();
            delete pg_db_trans_;
            pg_db_trans_ = NULL;
        }
    }
    catch ( pqxx::unique_violation & e )
    {
        cerr << "Unique_violation error: " << e.what() << endl;
        exit( EXIT_FAILURE);
    }
    catch( runtime_error & e )
    {
        cerr << "Runtime error: " << e.what() << endl;
        exit( EXIT_FAILURE );
    }
    catch( std::exception & e )
    {
        cerr << "Exception: " << e.what() << " select version from version;" << endl;
        exit( EXIT_FAILURE );
    }
    catch( ... )
    {
        cerr << "Unknown exception caught" << endl;
        exit( EXIT_FAILURE );
    }
    
    return 0;

}

int
DSCIOManager::dsc_disconnect_pg_db() {

    pg_db_conn_->disconnect();
    delete pg_db_conn_;
    // TODO Errors
    return 0;

}

void
DSCIOManager::dsc_activate_ouput_destination() {

    switch (output_) {
        case DAT:
            // Nothing to do
            break;
        case PG_DB:
            dsc_connect_pg_db();
            cout << "*** Database connected" << endl;
            break;
        default:
            // Should never get here!
            cerr << "Error: Unknown output destination." << endl;
            exit(1);
            break;
    }
}

void
DSCIOManager::dsc_deactivate_ouput_destination() {
    switch (output_) {
        case DAT:
            // Nothing to do
            break;
        case PG_DB:
            dsc_disconnect_pg_db();
            cout << "*** Database disconnected" << endl;
            break;
        default:
            // Should never get here!
            cerr << "Error: Unknown output destination." << endl;
            exit(1);
            break;
    }

}

void
DSCIOManager::dsc_import_input_from_source() {

    dsc_populate_files_vector();
    int file_counter=file_vector_.size();
    int file_failures = 0;
    int file_commit_status = 0;  // status of transaction commit
    int data_process_status = 0; // status of data processing
    
    string node = bfs::initial_path().filename().generic_string();
    string server = bfs::initial_path().parent_path().filename().generic_string();
    //fix potential sql injection
    replace(node.begin(), node.end(), '\'', '_');
    replace(server.begin(), server.end(), '\'', '_');
    //transform to 'internal' name
    replace(server.begin(), server.end(), '-', '_');
    replace_string(server, ".", "__");
    transform(server.begin(), server.end(), server.begin(), ::tolower);
    
    cout << "*** Processing server: " << server << endl;
    cout << "*** Processing node:   " << node << endl;
    cout << "*** Found:             " << file_counter << " " <<  file_extension_ << " files to process." << endl;
    
    for (std::vector<bfs::path>::const_iterator file_vector_it (file_vector_.begin()); file_vector_it != file_vector_.end(); ++file_vector_it) {
        if ( pg_db_conn_ != NULL ) {
            pg_db_trans_ = new pqxx::work( *pg_db_conn_ );
            if ( pg_db_trans_ == NULL ) {
                cerr << "Error starting transaction." << endl;
                exit(1);
            }
        }
        cout << endl << currentDateTime() << ": Starting:  " << (*file_vector_it).string() << endl;
        DSCDataManager* dsc_data_manager = new DSCDataManager(server, node, pg_db_trans_);
        file_commit_status = 0;
        
        switch (input_) {
            case XML: {
                // Partially read the xml. This is done using a policy tree,
                // which uses rapidxml to do the parsing. There is no validation
                // performed. Each array element is a "data unit" to send to the
                // data manager load function.
                dsc_xml_ = new boost::property_tree::ptree();
                // If reading fails, exception is thrown.
                try {
                  boost::property_tree::read_xml((*file_vector_it).string(), *dsc_xml_);
                  // We can't validate the XML but make this call the check that the file 
                  // at least has the correct top level node
                  try {
                    dsc_xml_->get_child("dscdata");
                  }
                  catch( std::exception & e ) {
                    cerr << "Error: XML file has no content: " << e.what() << endl;
                    data_process_status = 1;
                    break;					
                  }
                }
                catch( std::exception & e )
                {
                    cerr << "Exception reading XML file: " << e.what() << endl;
                    data_process_status = 1;
                    break;
                }
                catch( ... )
                {
                    cerr << "Unknown exception caught" << endl;
                    data_process_status = 1;
                    break;
                }
                // Get each array element and process it
                BOOST_FOREACH(boost::property_tree::ptree::value_type &array_element, dsc_xml_->get_child("dscdata")) {
                    data_process_status = dsc_data_manager->load(array_element, rssac_);
                    if (data_process_status) break;
                    data_process_status = dsc_data_manager->process();
                    if (data_process_status) break;
                }
                delete dsc_xml_;
            }
            break;
            case DAT: {
                // Call out to strategies to find out how this dat file 
                // needs to be processed.
                // F1, F2 and F3a dat files contain multiple "data units"
                vector<DSCStrategy*>::iterator strategy_it;
                string name = (*file_vector_it).filename().string();
                std::vector<DSCStrategy*> strategies_vector = DSCStrategyFactory::createStrategyDat(server, name);
                bool multi;
                
                if (strategies_vector.size() != 1 ) { 
                    cerr << "Error: Failed to find a single strategy for loading dat file " << (*file_vector_it).string() << " (found: " << strategies_vector.size() << ")" << endl;
                    // exit(1);
                    break;
                }
                
                strategy_it = strategies_vector.begin();
                multi = (*strategy_it)->is_dat_file_multi_unit();

                if ( multi ) {
                    // read the file one line at a time
                    bfs::fstream dat_file;
                    string dat_line;
                    dat_file.open((*file_vector_it), ios_base::in);
                    while (getline(dat_file, dat_line)) {
                        if ( dsc_data_manager->load(dat_line, strategy_it) !=0 ) continue;
                        data_process_status = dsc_data_manager->process(strategy_it);
                        if (data_process_status) break;
                    }
                } else {
                    // The entire file is one "data unit"
                    dsc_data_manager->load((*file_vector_it), strategy_it);
                    data_process_status = dsc_data_manager->process(strategy_it);
                    if (data_process_status) break;
                }
            }
            break;
            default:
                // Should never get here!
                cerr << "Error: Unknown input source" << endl;
                exit(1);
        }
        
        if ( pg_db_trans_ != NULL ) {  
                if ( data_process_status == 0 ) {
                    try {
                        pg_db_trans_->commit();
                    }             
                    catch( std::exception & e ) {
                        cerr << "Commit failed with exception message: " << e.what() << endl;    
                        file_commit_status = 1;             
                    }
                    catch( ... ) {
                        cerr << "Commit failed with general error" << endl;
                        file_commit_status = 1;
                    }
                }
                // Tidy up. If the processing was not successful the transaction will implicitly rollback
                delete pg_db_trans_;
                pg_db_trans_ = NULL;         
        }      
        
        delete dsc_data_manager;        
        
        // move the file to the done directory if all is well
        if (file_commit_status == 0 && data_process_status == 0 ) {
             dsc_move_file_to_done((*file_vector_it));        
        } else {
              dsc_handle_failed_file((*file_vector_it), data_process_status);
            file_failures++;
        }
                
        // Display helpful message. Flush to ensure that it is seen.
        file_counter--;
        cout << currentDateTime() << ": Completed: " << setw(50) << left << (*file_vector_it).string() << "\t" << file_counter << " remaining (" << file_failures << " failures)."<< endl;

        std::flush(cout);
    }
    
    // clear the file vector
    file_vector_.clear();
    
    // endl since the previous cout used \r
    cout << endl;

}

void
DSCIOManager::dsc_populate_files_vector() {
    
    std::string start_of_search_path;
    switch (input_) {
        case XML: 
            start_of_search_path = "./incoming";
            file_extension_ = ".xml";
            break;
        case DAT:
            start_of_search_path = ".";
            file_extension_ = ".dat";
            break;
        default:
            // Should never get here!
            cerr << "Error: Unknown input source" << endl;
            exit(1);
            break;
    }
    
    bfs::path p_start (start_of_search_path);
    bfs::path::iterator path_it;

    // iterators for iterating over all the paths/files in the data directory
    bfs::recursive_directory_iterator it(p_start), end_it;
    try {
        if (bfs::exists(p_start)) {
            if (bfs::is_directory(p_start)) {
                BOOST_FOREACH( bfs::path const &p_search, make_pair(it, end_it) ) {                 
                    if ( p_search.string().find("done") == string::npos ) {
                        if ( ! bfs::is_directory(p_search) ) {
                            if ( p_search.extension() == file_extension_ ) {
                                if (! exclude_file(p_search, start_of_search_path) ) {
                                        file_vector_.push_back(p_search);                                   
                                }
                            }
                        }
                    }
                }
                sort(file_vector_.begin(), file_vector_.end());
            } else {
                cout << p_start << " exists, but is not a directory\n";
                exit(1);
            }
        } else {
            cout << p_start << " does not exist\n";
            exit(1);
        }
    }
    catch (const bfs::filesystem_error& ex) {
        cout << "FS Error: " << ex.what() << endl;
        exit(1);
    }
    catch (std::exception &e) {
        cerr << "Error creating file vector: " << e.what() << endl;
        exit(1);
    }
    
}

void
DSCIOManager::dsc_move_file_to_done(bfs::path path_src) {
    
    bfs::path path_done ("./done");
    std::string done_sub_dir;
    bfs::path path_date;
    bfs::path path_done_date;
    bfs::path path_newfile;
    
    try {
        switch (input_) {
            case XML: 
                done_sub_dir = "dscdata";
                path_date = path_src.parent_path().stem(); //./incoming/yyyymmdd/*.xml --> yyyymmdd
                break;
            case DAT:
                done_sub_dir = "dat";
                path_date = path_src.parent_path().stem(); // ./yyyymmdd/*.dat --> yyyymmdd
                break;
            default:
                // Should never get here!
                cerr << "Error: Unknown input source" << endl;
                exit(1);
                break;
        }
        path_done_date = path_done/path_date/done_sub_dir;
        if (!bfs::exists(path_done_date)) {
                bfs::create_directories(path_done_date);
        }
        path_newfile = path_done_date/path_src.filename();
        bfs::rename(path_src, path_newfile);
        cout << currentDateTime() << ": Moved:     " << path_src.string() << " to " <<  path_newfile.string() << endl;
    }
    catch (const bfs::filesystem_error& ex) {
        cout << "FS Error: " << ex.what() << endl;
        exit(1);
    }
    catch (std::exception &e) {
        cerr << "Error moving file to done: " << e.what() << endl;
        exit(1);
    }
 
}

void
DSCIOManager::dsc_handle_failed_file(bfs::path path_src, int process) {
    
    bfs::path path_newfile;  
    std::string failed = ".failed.";
    std::string reason_string = process ? " Failed to process data " : "Failed to commit data to DB";
    
    try {
        path_newfile = path_src.string() + failed + bfs::unique_path().string();
        bfs::rename(path_src, path_newfile);
        cout << currentDateTime() << ": Error: failed to process: " << path_src.string() << "Reason: " << reason_string << endl;
        cout << currentDateTime() << ": File renamed to:          " << path_newfile.string() << endl;
    }
    catch (const bfs::filesystem_error& ex) {
        cout << "FS Error: " << ex.what() << endl;
        exit(1);
    }
    catch (std::exception &e) {
        cerr << "Error handling failed file: " << e.what() << endl;
        exit(1);
    }
 
}

bool 
DSCIOManager::exclude_file(bfs::path path_src, const string& start_of_search_path) {

    if ( input_ != XML || processing_start_date_ == NULL ) {
        return false;
    }

    // Only do this test on files under './incoming'
    if ( path_src.parent_path().parent_path().string().compare(start_of_search_path) == 0 ) {
        struct tm tm  = { 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL };
        if ( strptime(path_src.parent_path().filename().string().c_str(), "%Y-%m-%d", &tm) != NULL ) {
            time_t dir_time = mktime(&tm);
            //cout << "Diff time is " << difftime(*processing_start_date_, dir_time) << endl ;
            if ( difftime(*processing_start_date_, dir_time) > 0 )  {
                cout << "File " << path_src.string() << " excluded from processing as it is earlier than the processing start date" << endl;
                return true;
            } else {
                return false;
            }
        }                     
    }
    return false;

}