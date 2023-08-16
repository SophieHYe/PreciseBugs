/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
package com.rtds.svc;

import com.rtds.jpa.CaptureType;
import java.util.List;
import javax.enterprise.context.ApplicationScoped;
import javax.inject.Inject;
import javax.persistence.EntityManager;
import javax.transaction.Transactional;

/**
 *
 * @author jdh
 */
@ApplicationScoped
@Transactional
public class CaptureTypeService
{
    @Inject
    EntityManager em;
    
    public void createOrUpdateCaptureType( CaptureType value )
    {
        if( value == null || value.getLabel() == null || value.getUrlSuffix() == null )
        {
            throw new IllegalArgumentException( "The CaptureType, it's label and url_suffix must not be null." );
        }
        
        CaptureType persistent = em.find( CaptureType.class, value.getUrlSuffix() );
        
        if( persistent != null )
        {
            persistent.setLabel( value.getLabel() );
            persistent.setCaptureFilter( value.getCaptureFilter() );
        }
        else
        {
            em.persist( value );
        }
    }
    
    public String findFilter( String url_suffix )
    {
        if( url_suffix == null )
        {
            throw new IllegalArgumentException( "The url_suffix must not be null." );
        }
        
        CaptureType type = em.find( CaptureType.class, url_suffix );
        
        if( type == null )
        {
            throw new IllegalArgumentException( "The url_suffix must exist in the database." );
        }
        
        // It is okay for the capture filter itself to be null, but the CaptureType
        // must be in the database, otherwise the user could effectively forge
        // a capture filter for "all" just by requesting a non-existent filter.
        
        return type.getCaptureFilter();
    }
    
    public CaptureType find( String url_suffix )
    {
        return em.find( CaptureType.class, url_suffix );
    }
    
    public List<CaptureType> list()
    {
        return em.createQuery( "select ct from CaptureType ct", CaptureType.class ).getResultList();
    }
    
    public void deleteCaptureType( String url_suffix )
    {
        if( url_suffix == null )
        {
            throw new IllegalArgumentException( "The url_suffix must not be null." );
        }
        
        CaptureType persistent = em.find( CaptureType.class, url_suffix );
        
        if( persistent != null )
        {
            em.remove( persistent );
        }
    }
    
}
