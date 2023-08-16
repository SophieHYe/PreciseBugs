package com.nexblocks.authguard.service.model;

import org.immutables.value.Value;

@Value.Immutable
@BOStyle
public interface UserIdentifier {
    Long getId(); // only useful for relational DBs
    Type getType();
    String getIdentifier();
    boolean isActive();

    enum Type {
        USERNAME,
        EMAIL,
        PHONE_NUMBER
    }
}
